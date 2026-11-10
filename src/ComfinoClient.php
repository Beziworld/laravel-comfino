<?php

declare(strict_types=1);

namespace Beziworld\Comfino;

use Beziworld\Comfino\Data\Cart;
use Beziworld\Comfino\Data\CreatedOrder;
use Beziworld\Comfino\Data\FinancialProduct;
use Beziworld\Comfino\Data\LoanOption;
use Beziworld\Comfino\Data\Notification;
use Beziworld\Comfino\Data\Order;
use Beziworld\Comfino\Data\OrderRequest;
use Beziworld\Comfino\Data\ProductQuery;
use Beziworld\Comfino\Data\ProductType;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Enums\ProductListType;
use Beziworld\Comfino\Exceptions\ApiException;
use Beziworld\Comfino\Exceptions\InvalidNotification;
use Beziworld\Comfino\Support\Json;
use Beziworld\Comfino\Support\Signature;
use Beziworld\Comfino\Support\Widget;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;

/**
 * Client of the ComfinoPay REST API for one shop account.
 *
 * Every request carries the Api-Key header. Writes (loan applications,
 * validation, cancellation) are sent exactly once; reads are retried on
 * connection errors and HTTP 5xx. HTTP 429 is reported with Retry-After
 * and never retried automatically.
 */
class ComfinoClient
{
    private ?string $fetchedWidgetKey = null;

    /**
     * @param (Closure(ComfinoConfig): ?string)|null $notifyUrlResolver Builds the default notification URL of the connection.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ComfinoConfig $config,
        private readonly ?Closure $notifyUrlResolver = null,
    ) {}

    public function config(): ComfinoConfig
    {
        return $this->config;
    }

    public function connectionName(): ?string
    {
        return $this->config->name;
    }

    /**
     * Notification URL sent with new loan applications: the configured one or the package route of the connection.
     */
    public function notifyUrl(): ?string
    {
        return $this->config->notifyUrl ?? ($this->notifyUrlResolver)?->__invoke($this->config);
    }

    /**
     * Financial products available for a loan amount (in grosz) or for detailed criteria.
     *
     * @return list<FinancialProduct>
     */
    public function financialProducts(ProductQuery|int $query): array
    {
        $query = $query instanceof ProductQuery ? $query : new ProductQuery($query);

        return $this->products($this->call('GET', '/v1/financial-products', $query->toQuery(), retry: true));
    }

    /**
     * Financial products calculated for a cart, e.g. leasing offers that depend on the cart content.
     *
     * The loan amount defaults to the cart total.
     *
     * @return list<FinancialProduct>
     */
    public function financialProductsForCart(Cart $cart, ProductQuery|int|null $query = null): array
    {
        $query = $query instanceof ProductQuery ? $query : new ProductQuery($query ?? $cart->totalAmount);

        return $this->products($this->call('POST', '/v1/financial-products', $query->toQuery(), Json::encode($cart->toArray()), retry: true));
    }

    /**
     * Parameters of the best possible offer (the lowest instalment) for a loan amount in grosz.
     */
    public function bestOffer(int $loanAmount): LoanOption
    {
        new ProductQuery($loanAmount);

        $payload = $this->call('GET', '/v1/best-possible-loan-parameters', ['loanAmount' => $loanAmount], retry: true);

        return LoanOption::fromArray(is_array($payload) ? $payload : [], $this->config->currency);
    }

    /**
     * Registers a loan application; redirect the customer to applicationUrl of the result.
     */
    public function createOrder(OrderRequest $order): CreatedOrder
    {
        $payload = $this->sendOrder($order, false);
        $created = CreatedOrder::fromArray(is_array($payload) ? $payload : []);

        if ($created->applicationUrl === '' || ! str_starts_with(strtolower($created->applicationUrl), 'http')) {
            throw ApiException::unexpectedResponse('the loan application has no applicationUrl');
        }

        return $created;
    }

    /**
     * Validates a loan application without registering it (simulation mode of the official client).
     *
     * @return array<array-key, mixed> Response body, usually empty.
     *
     * @throws ApiException With the validation errors when ComfinoPay rejects the data.
     */
    public function validateOrder(OrderRequest $order): array
    {
        $payload = $this->sendOrder($order, true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * Current state of a loan application by the shop orderId.
     *
     * ComfinoPay recommends status notifications as the primary source of
     * status changes; use this read for reconciliation.
     */
    public function order(string $orderId): Order
    {
        $id = $this->id($orderId);
        $payload = $this->call('GET', '/v1/orders/' . $id, retry: true);

        return Order::fromArray(is_array($payload) ? $payload : [], $this->config->currency, $this->config->timezone, trim($orderId));
    }

    /**
     * Cancels a loan application at any stage (status CANCELLED_BY_SHOP).
     *
     * @return OrderStatus|null Status reported in the response body, CANCELLED_BY_SHOP for an empty HTTP 204 answer.
     */
    public function cancelOrder(string $orderId): ?OrderStatus
    {
        $payload = $this->call('PUT', '/v1/orders/' . $this->id($orderId) . '/cancel');
        $status = is_array($payload) && is_string($payload['status'] ?? null) ? $payload['status'] : null;

        return $status !== null ? OrderStatus::tryFrom($status) : OrderStatus::CancelledByShop;
    }

    /**
     * Financial product types enabled for the shop account.
     *
     * @return list<ProductType>
     */
    public function productTypes(ProductListType $listType = ProductListType::Widget): array
    {
        $payload = $this->call('GET', '/v1/product-types', ['listType' => $listType->value], retry: true);

        return ProductType::listFromArray(is_array($payload) ? $payload : []);
    }

    /**
     * Widget types available for the shop, as a code => name map.
     *
     * @return array<string, string>
     */
    public function widgetTypes(): array
    {
        $payload = $this->call('GET', '/widget/v1/widget-types', retry: true);
        $types = [];

        foreach (is_array($payload) ? $payload : [] as $code => $name) {
            $types[(string) $code] = is_string($name) ? $name : (string) $code;
        }

        return $types;
    }

    /**
     * Creditor codes per financial product type, used to show lender logos.
     *
     * @return array<string, list<string>>
     */
    public function creditors(): array
    {
        $payload = $this->call('GET', '/v1/creditors', retry: true);
        $creditors = [];

        foreach (is_array($payload) ? $payload : [] as $type => $codes) {
            $creditors[(string) $type] = array_values(array_filter(is_array($codes) ? $codes : [], is_string(...)));
        }

        return $creditors;
    }

    /**
     * Whether the API key belongs to an activated shop account.
     */
    public function isAccountActive(): bool
    {
        $payload = $this->call('GET', '/v1/user/is-active', retry: true);

        if (! is_bool($payload)) {
            throw ApiException::unexpectedResponse('the account status is not a boolean');
        }

        return $payload;
    }

    /**
     * Public widget key: the configured one, otherwise fetched from the API once per client instance.
     */
    public function widgetKey(): string
    {
        return $this->config->widgetKey ?? ($this->fetchedWidgetKey ??= $this->fetchWidgetKey());
    }

    /**
     * Widget key fetched from GET /v1/widget-key.
     */
    public function fetchWidgetKey(): string
    {
        $payload = $this->call('GET', '/v1/widget-key', retry: true);

        if (! is_string($payload) || $payload === '') {
            throw ApiException::unexpectedResponse('the widget key is not a string');
        }

        return $payload;
    }

    /**
     * Product page widget builder of this connection.
     */
    public function widget(?string $widgetKey = null): Widget
    {
        return new Widget($this->config, $widgetKey ?? $this->widgetKey());
    }

    /**
     * Short-lived (15 minutes) authentication token of the checkout paywall iframe.
     */
    public function paywallAuthKey(?int $timestamp = null): string
    {
        return Signature::paywallAuthKey($this->widgetKey(), $this->config->apiKey, $timestamp ?? time());
    }

    /**
     * Whether a CR-Signature header matches the raw notification body.
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        try {
            Signature::verify($rawBody, $signature, $this->config->apiKey);
        } catch (InvalidNotification) {
            return false;
        }

        return true;
    }

    /**
     * Verifies the CR-Signature header and parses a status notification.
     *
     * @throws InvalidNotification When the signature is missing or invalid, or the body is malformed.
     */
    public function parseNotification(string $rawBody, ?string $signature, bool $verifySignature = true): Notification
    {
        if ($verifySignature) {
            Signature::verify($rawBody, $signature, $this->config->apiKey);
        }

        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidNotification::malformed();
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw InvalidNotification::malformed();
        }

        if (! is_string($payload['status'] ?? null) || $payload['status'] === '') {
            throw InvalidNotification::malformed('status is missing');
        }

        if (! is_string($payload['externalId'] ?? null) && ! is_int($payload['externalId'] ?? null)) {
            throw InvalidNotification::malformed('externalId is missing');
        }

        return Notification::fromArray($payload, $this->config->timezone);
    }

    /**
     * Sends a loan application with the signing headers of the official client.
     */
    private function sendOrder(OrderRequest $order, bool $simulation): mixed
    {
        $body = $order->toArray($this->config->returnUrl, $this->notifyUrl(), $simulation);

        /** @var array<array-key, mixed> $cart */
        $cart = $body['cart'];

        /** @var array<array-key, mixed> $customer */
        $customer = $body['customer'];

        return $this->call('POST', '/v1/orders', body: Json::encode($body), headers: Signature::orderHeaders($cart, $customer, $this->config->apiKey));
    }

    /**
     * @return list<FinancialProduct>
     */
    private function products(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw ApiException::unexpectedResponse('the financial products are not a list');
        }

        $products = [];

        foreach ($payload as $product) {
            if (is_array($product)) {
                $products[] = FinancialProduct::fromArray($product, $this->config->currency);
            }
        }

        return $products;
    }

    /**
     * @param array<string, int|string> $query
     * @param array<string, string> $headers
     */
    private function call(string $method, string $path, array $query = [], ?string $body = null, array $headers = [], bool $retry = false): mixed
    {
        $attempts = $retry ? $this->config->retries + 1 : 1;
        $attempt = 0;
        $url = $this->config->apiUrl() . $path . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');

        while (true) {
            $attempt++;

            try {
                $response = $this->send($method, $url, $body, $headers);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    $this->pause();

                    continue;
                }

                throw ApiException::transport($exception);
            }

            if ($response->serverError() && $attempt < $attempts) {
                $this->pause();

                continue;
            }

            $raw = trim($response->body());
            $payload = null;

            if ($raw !== '') {
                try {
                    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    if ($response->successful()) {
                        throw ApiException::unexpectedResponse('the body is not JSON', $response->status());
                    }
                }
            }

            if ($response->failed()) {
                $retryAfter = $response->header('Retry-After');

                throw ApiException::fromResponse(is_array($payload) ? $payload : [], $response->status(), is_numeric($retryAfter) ? (int) $retryAfter : null);
            }

            return $payload;
        }
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws ConnectionException
     */
    private function send(string $method, string $url, ?string $body, array $headers): Response
    {
        $request = $this->http
            ->createPendingRequest()
            ->withHeaders($headers + [
                'Api-Key' => $this->config->apiKey,
                'Api-Language' => $this->config->language,
                'Api-Currency' => $this->config->currency,
            ])
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withUserAgent('beziworld-laravel-comfino/1.0');

        if ($body !== null) {
            $request = $request->withBody($body, 'application/json');
        } else {
            $request = $request->withHeaders(['Content-Type' => 'application/json']);
        }

        return $request->send($method, $url);
    }

    private function pause(): void
    {
        if ($this->config->retryDelay > 0) {
            Sleep::usleep($this->config->retryDelay * 1000);
        }
    }

    private function id(string $orderId): string
    {
        $orderId = trim($orderId);

        if ($orderId === '') {
            throw new InvalidArgumentException('ComfinoPay orderId must not be empty.');
        }

        return rawurlencode($orderId);
    }
}
