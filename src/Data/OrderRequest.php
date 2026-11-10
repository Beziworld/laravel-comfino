<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Exceptions\InvalidConfiguration;
use Beziworld\Comfino\Exceptions\InvalidOrder;
use stdClass;

/**
 * Loan application (order) sent to POST /v1/orders.
 *
 * orderId is the shop order identifier (max. 36 characters); ComfinoPay
 * reports it back as externalId in status notifications. returnUrl and
 * notifyUrl default to the connection configuration and the package
 * notification route. loanTerm and loanType preselect an offer;
 * allowedProductTypes limits the offers shown on the application form
 * (null removes the limit, an empty list blocks every offer).
 */
final readonly class OrderRequest
{
    /**
     * @param list<LoanType|string>|null $allowedProductTypes
     * @param list<array{type: LoanType|string, minTerm?: int, maxTerm?: int, terms?: list<int>}>|null $allowedProductsConfig Per product term constraints, as in the official client.
     * @param array<string, mixed> $extra Additional top level body fields sent as given.
     *
     * @throws InvalidOrder When a documented length constraint is violated.
     */
    public function __construct(
        public string $orderId,
        public Cart $cart,
        public Customer $customer,
        public ?string $returnUrl = null,
        public ?string $notifyUrl = null,
        public ?int $loanTerm = null,
        public LoanType|string|null $loanType = null,
        public ?array $allowedProductTypes = null,
        public ?string $accountNumber = null,
        public ?string $transferTitle = null,
        public ?string $sellerTaxId = null,
        public ?string $promoCode = null,
        public ?array $allowedProductsConfig = null,
        public array $extra = [],
    ) {
        $length = mb_strlen(trim($orderId));

        if ($length === 0 || $length > 36) {
            throw InvalidOrder::because('orderId must have 1 to 36 characters');
        }

        if ($accountNumber !== null && preg_match('/^\d{1,26}$/', (string) preg_replace('/[\s-]+/', '', $accountNumber)) !== 1) {
            throw InvalidOrder::because('accountNumber must have at most 26 digits');
        }

        if ($transferTitle !== null && mb_strlen($transferTitle) > 140) {
            throw InvalidOrder::because('transferTitle may have at most 140 characters');
        }
    }

    /**
     * Request body; URLs missing in the request are taken from the given defaults.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidConfiguration When no return URL is available.
     * @throws InvalidOrder When a URL is longer than 255 characters.
     */
    public function toArray(?string $defaultReturnUrl = null, ?string $defaultNotifyUrl = null, bool $simulation = false): array
    {
        $returnUrl = $this->returnUrl ?? $defaultReturnUrl ?? throw InvalidConfiguration::missingUrl('returnUrl');
        $notifyUrl = $this->notifyUrl ?? $defaultNotifyUrl;

        foreach (['returnUrl' => $returnUrl, 'notifyUrl' => $notifyUrl] as $field => $url) {
            if ($url !== null && strlen($url) > 255) {
                throw InvalidOrder::because($field . ' may have at most 255 characters');
            }
        }

        $loanParameters = array_filter([
            'term' => $this->loanTerm,
            'type' => $this->loanType !== null ? LoanType::values([$this->loanType])[0] : null,
            'allowedProductTypes' => $this->allowedProductTypes !== null ? LoanType::values($this->allowedProductTypes) : null,
        ], static fn(mixed $value): bool => $value !== null);

        $promoCode = $this->promoCode !== null ? trim($this->promoCode) : null;

        $body = array_filter([
            'orderId' => trim($this->orderId),
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $returnUrl,
            'loanParameters' => $loanParameters === [] ? new stdClass() : $loanParameters,
            'cart' => $this->cart->toArray(),
            'customer' => $this->customer->toArray(),
            'seller' => $this->sellerTaxId !== null && $this->sellerTaxId !== '' ? ['taxId' => $this->sellerTaxId] : null,
            'accountNumber' => $this->accountNumber !== null ? preg_replace('/[\s-]+/', '', $this->accountNumber) : null,
            'transferTitle' => $this->transferTitle,
            'promoCode' => $promoCode !== '' ? $promoCode : null,
            'simulation' => $simulation ? true : null,
            'allowedProductsConfig' => $this->allowedProductsConfig !== null ? self::productsConfig($this->allowedProductsConfig) : null,
        ], static fn(mixed $value): bool => $value !== null);

        return array_merge($body, $this->extra);
    }

    /**
     * @param list<array{type: LoanType|string, minTerm?: int, maxTerm?: int, terms?: list<int>}> $config
     * @return list<array<string, mixed>>
     */
    private static function productsConfig(array $config): array
    {
        return array_map(static fn(array $entry): array => array_filter([
            'type' => LoanType::values([$entry['type']])[0],
            'maxTerm' => $entry['maxTerm'] ?? null,
            'minTerm' => $entry['minTerm'] ?? null,
            'terms' => $entry['terms'] ?? null,
        ], static fn(mixed $value): bool => $value !== null), $config);
    }
}
