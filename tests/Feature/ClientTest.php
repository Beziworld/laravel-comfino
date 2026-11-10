<?php

declare(strict_types=1);

use Beziworld\Comfino\Data\Cart;
use Beziworld\Comfino\Data\CartItem;
use Beziworld\Comfino\Data\Customer;
use Beziworld\Comfino\Data\OrderRequest;
use Beziworld\Comfino\Data\ProductQuery;
use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Enums\ProductListType;
use Beziworld\Comfino\Exceptions\ApiException;
use Beziworld\Comfino\Exceptions\InvalidConfiguration;
use Beziworld\Comfino\Facades\Comfino;
use Beziworld\Comfino\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function sampleOrder(array $overrides = []): OrderRequest
{
    return new OrderRequest(...array_merge([
        'orderId' => 'ZAM-001',
        'cart' => Cart::make([new CartItem('Lenovo Ideapad 120S-14IAP', 246000, ean: '9002490100070', externalId: '123')]),
        'customer' => new Customer('Jan', 'Kowalski', 'customer@example.com', '+48 312 213 213', '83.20.11.4'),
    ], $overrides));
}

function hasApiHeaders(Request $request): bool
{
    return $request->hasHeader('Api-Key', TestCase::API_KEY)
        && $request->hasHeader('Api-Language', 'pl')
        && $request->hasHeader('Api-Currency', 'PLN')
        && ! $request->hasHeader('Authorization');
}

it('lists financial products for an amount', function (): void {
    Http::fake(['api-ecommerce.craty.pl/v1/financial-products*' => Http::response(comfinoFixture('financial-products'))]);

    $products = Comfino::financialProducts(120000);

    expect($products)->toHaveCount(2)
        ->and($products[0]->type)->toBe(LoanType::InstallmentsZeroPercent)
        ->and($products[0]->defaultOption->instalment->decimal())->toBe('12.00')
        ->and($products[0]->terms())->toBe([3, 6, 10])
        ->and($products[1]->option(12)?->toPay->amount)->toBe(134394)
        ->and($products[1]->defaultOption->rrsoPercent())->toBe('23.84')
        ->and($products[1]->lowestInstalment()->term)->toBe(48);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
        && $request->url() === TestCase::SANDBOX . '/v1/financial-products?loanAmount=120000'
        && hasApiHeaders($request));
});

it('sends every documented query parameter', function (): void {
    Http::fake(['*' => Http::response([])]);

    Comfino::financialProducts(new ProductQuery(
        loanAmount: 120000,
        loanTerm: 6,
        loanType: LoanType::PayLater,
        loanTypeSelected: 'convenient_installments',
        productTypes: [LoanType::ConvenientInstallments, 'PAY_LATER'],
        taxId: '5250001009',
    ));

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::SANDBOX
        . '/v1/financial-products?loanAmount=120000&loanTerm=6&loanType=PAY_LATER&loanTypeSelected=CONVENIENT_INSTALLMENTS'
        . '&productTypes=CONVENIENT_INSTALLMENTS%2CPAY_LATER&taxId=5250001009');
});

it('posts the cart for cart dependent offers', function (): void {
    Http::fake(['*' => Http::response(comfinoFixture('financial-products'))]);

    $cart = Cart::make([new CartItem('Laptop', 246000, quantity: 2)], deliveryCost: 1230);

    expect(Comfino::financialProductsForCart($cart, new ProductQuery(493230, productTypes: [LoanType::Leasing])))->toHaveCount(2);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), 'loanAmount=493230&productTypes=LEASING')
        && $request['totalAmount'] === 493230
        && $request['deliveryCost'] === 1230
        && $request['products'][0]['quantity'] === 2);
});

it('reads the best possible offer', function (): void {
    Http::fake(['*/v1/best-possible-loan-parameters*' => Http::response(['instalmentAmount' => 2883, 'loanTerm' => 48, 'toPay' => 138384])]);

    $offer = Comfino::bestOffer(100000);

    expect($offer->term)->toBe(48)
        ->and($offer->instalment->amount)->toBe(2883)
        ->and((string) $offer->toPay)->toBe('1383.84 PLN');

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::SANDBOX . '/v1/best-possible-loan-parameters?loanAmount=100000');
});

it('creates a signed loan application with the default URLs', function (): void {
    Http::fake(['*/v1/orders' => Http::response([
        'status' => 'CREATED',
        'externalId' => 'ZAM-001',
        'applicationUrl' => 'https://app.craty.pl/application/abc123',
    ], 201)]);

    $created = Comfino::createOrder(sampleOrder(['loanTerm' => 10, 'loanType' => LoanType::InstallmentsZeroPercent]));

    expect($created->status)->toBe(OrderStatus::Created)
        ->and($created->externalId)->toBe('ZAM-001')
        ->and($created->applicationUrl)->toBe('https://app.craty.pl/application/abc123');

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);
        $cartHash = hash('sha3-256', json_encode($body['cart'], JSON_PRESERVE_ZERO_FRACTION));
        $customerHash = hash('sha3-256', json_encode($body['customer'], JSON_PRESERVE_ZERO_FRACTION));

        return $request->method() === 'POST'
            && $request->url() === TestCase::SANDBOX . '/v1/orders'
            && hasApiHeaders($request)
            && $request->hasHeader('Content-Type', 'application/json')
            && $body['orderId'] === 'ZAM-001'
            && $body['returnUrl'] === 'https://shop.example.com/comfino/return'
            && $body['notifyUrl'] === 'https://shop.example.com/comfino/notification/default'
            && $body['loanParameters'] === ['term' => 10, 'type' => 'INSTALLMENTS_ZERO_PERCENT']
            && $body['cart'] === ['products' => [['name' => 'Lenovo Ideapad 120S-14IAP', 'quantity' => 1, 'price' => 246000, 'ean' => '9002490100070', 'externalId' => '123']], 'totalAmount' => 246000]
            && $body['customer'] === ['firstName' => 'Jan', 'lastName' => 'Kowalski', 'email' => 'customer@example.com', 'phoneNumber' => '312213213', 'ip' => '83.20.11.4']
            && ! array_key_exists('simulation', $body)
            && $request->hasHeader('Comfino-Cart-Hash', $cartHash)
            && $request->hasHeader('Comfino-Customer-Hash', $customerHash)
            && $request->hasHeader('Comfino-Order-Signature', hash('sha3-256', $cartHash . $customerHash . TestCase::API_KEY));
    });
});

it('sends an empty object as loan parameters and the optional fields', function (): void {
    Http::fake(['*' => Http::response(['status' => 'CREATED', 'externalId' => 'ZAM-002', 'applicationUrl' => 'https://app.craty.pl/x'], 201)]);

    Comfino::createOrder(sampleOrder([
        'orderId' => 'ZAM-002',
        'returnUrl' => 'https://shop.example.com/thanks',
        'notifyUrl' => 'https://shop.example.com/hook',
        'accountNumber' => '75 1140 1124 4753 6191 9814 8640',
        'transferTitle' => 'Płatność za zamówienie ZAM-002',
        'sellerTaxId' => '5250001009',
        'promoCode' => '  ',
        'allowedProductsConfig' => [['type' => LoanType::ConvenientInstallments, 'maxTerm' => 24]],
    ]));

    Http::assertSent(fn(Request $request): bool => str_contains($request->body(), '"loanParameters":{}')
        && $request['returnUrl'] === 'https://shop.example.com/thanks'
        && $request['notifyUrl'] === 'https://shop.example.com/hook'
        && $request['accountNumber'] === '75114011244753619198148640'
        && $request['transferTitle'] === 'Płatność za zamówienie ZAM-002'
        && $request['seller'] === ['taxId' => '5250001009']
        && ! array_key_exists('promoCode', $request->data())
        && $request['allowedProductsConfig'] === [['type' => 'CONVENIENT_INSTALLMENTS', 'maxTerm' => 24]]);
});

it('limits offers with allowed product types', function (): void {
    Http::fake(['*' => Http::response(['status' => 'CREATED', 'externalId' => 'ZAM-001', 'applicationUrl' => 'https://app.craty.pl/x'], 201)]);

    Comfino::createOrder(sampleOrder(['allowedProductTypes' => [LoanType::InstallmentsZeroPercent, LoanType::PayLater]]));

    Http::assertSent(fn(Request $request): bool => $request['loanParameters'] === ['allowedProductTypes' => ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER']]);
});

it('never retries a loan application', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Internal error'], 500)]);

    expect(fn() => Comfino::createOrder(sampleOrder()))->toThrow(ApiException::class, 'Internal error');

    Http::assertSentCount(1);
});

it('reports validation errors of a loan application', function (): void {
    Http::fake(['*' => Http::response(['errors' => ['cart.totalAmount' => 'Invalid total', 'customer.phoneNumber' => ['Too short', 'Digits only']]], 400)]);

    try {
        Comfino::createOrder(sampleOrder());
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->httpStatus())->toBe(400)
            ->and($exception->isValidationError())->toBeTrue()
            ->and($exception->errors())->toBe(['cart.totalAmount' => ['Invalid total'], 'customer.phoneNumber' => ['Too short', 'Digits only']])
            ->and($exception->getMessage())->toContain('cart.totalAmount: Invalid total');
    }
});

it('rejects a created order without an application URL', function (): void {
    Http::fake(['*' => Http::response(['status' => 'CREATED', 'externalId' => 'ZAM-001'], 201)]);

    Comfino::createOrder(sampleOrder());
})->throws(ApiException::class, 'applicationUrl');

it('requires a return URL', function (): void {
    config()->set('comfino.return_url', null);
    Comfino::purge();
    Http::fake();

    Comfino::createOrder(sampleOrder());
})->throws(InvalidConfiguration::class, 'returnUrl');

it('validates a loan application in simulation mode', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    expect(Comfino::validateOrder(sampleOrder()))->toBe([]);

    Http::assertSent(fn(Request $request): bool => $request['simulation'] === true && $request->hasHeader('Comfino-Order-Signature'));
});

it('reads a loan application', function (): void {
    Http::fake(['*/v1/orders/ZAM%2F001' => Http::response(comfinoFixture('order'))]);

    $order = Comfino::order('ZAM/001');

    expect($order->status)->toBe(OrderStatus::WaitingForConfirmation)
        ->and($order->orderId)->toBe('ZAM-001')
        ->and($order->createdAt?->toIso8601String())->toBe('2026-11-10T09:15:00+01:00')
        ->and($order->loanAmount?->decimal())->toBe('2460.00')
        ->and($order->loanTerm)->toBe(10)
        ->and($order->loanType)->toBe(LoanType::InstallmentsZeroPercent)
        ->and($order->allowedProductTypes)->toBe(['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER'])
        ->and($order->totalAmount?->amount)->toBe(246000);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET' && hasApiHeaders($request));
});

it('retries reads on server errors and connection failures', function (): void {
    config()->set('comfino.retries', 2);
    Comfino::purge();

    Http::fakeSequence('*')
        ->pushResponse(Http::response('', 503))
        ->pushFailedConnection()
        ->push(comfinoFixture('order'));

    expect(Comfino::order('ZAM-001')->statusValue)->toBe('WAITING_FOR_CONFIRMATION');

    Http::assertSentCount(3);
});

it('reports transport failures after the retries', function (): void {
    Http::fake(fn() => throw new ConnectionException('timed out'));

    Comfino::order('ZAM-001');
})->throws(ApiException::class, 'Could not connect to ComfinoPay: timed out');

it('reports a missing application as not found', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Order not found'], 404)]);

    try {
        Comfino::order('ZAM-404');
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->isNotFound())->toBeTrue()
            ->and($exception->errors())->toBe(['_' => ['Order not found']]);
    }
});

it('does not retry rate limited reads and exposes Retry-After', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Too many requests'], 429, ['Retry-After' => '30'])]);

    try {
        Comfino::financialProducts(100000);
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->isRateLimited())->toBeTrue()
            ->and($exception->retryAfter())->toBe(30)
            ->and($exception->getMessage())->toContain('retry after 30 seconds');
    }

    Http::assertSentCount(1);
});

it('reports a wrong API key', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Authorization failed'], 401)]);

    try {
        Comfino::isAccountActive();
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->isUnauthorized())->toBeTrue()
            ->and($exception->getMessage())->toBe('ComfinoPay error (HTTP 401): Authorization failed');
    }
});

it('cancels a loan application', function (): void {
    Http::fake(['*/v1/orders/ZAM-001/cancel' => Http::response(['status' => 'CANCELLED_BY_SHOP'])]);

    expect(Comfino::cancelOrder('ZAM-001'))->toBe(OrderStatus::CancelledByShop);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT' && $request->body() === '' && hasApiHeaders($request));
});

it('accepts an empty cancellation answer and never retries it', function (): void {
    Http::fake(['*' => Http::response('', 204)]);

    expect(Comfino::cancelOrder('ZAM-001'))->toBe(OrderStatus::CancelledByShop);

    Http::assertSentCount(1);
});

it('reads product types, widget types, creditors and the account status', function (): void {
    Http::fake([
        '*/v1/product-types*' => Http::response(['INSTALLMENTS_ZERO_PERCENT' => ['Raty 0%', 'Raty zero procent'], 'NEW_TYPE' => ['Nowy', 'Nowy typ']]),
        '*/widget/v1/widget-types' => Http::response(['standard' => 'Baner standardowy', 'classic' => 'Baner klasyczny']),
        '*/v1/creditors' => Http::response(['INSTALLMENTS_ZERO_PERCENT' => ['bank-a', 'bank-b']]),
        '*/v1/user/is-active' => Http::response('true'),
    ]);

    $types = Comfino::productTypes(ProductListType::Paywall);

    expect($types)->toHaveCount(2)
        ->and($types[0]->type)->toBe(LoanType::InstallmentsZeroPercent)
        ->and($types[0]->publicName)->toBe('Raty zero procent')
        ->and($types[1]->type)->toBeNull()
        ->and(Comfino::widgetTypes())->toBe(['standard' => 'Baner standardowy', 'classic' => 'Baner klasyczny'])
        ->and(Comfino::creditors())->toBe(['INSTALLMENTS_ZERO_PERCENT' => ['bank-a', 'bank-b']])
        ->and(Comfino::isAccountActive())->toBeTrue();

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::SANDBOX . '/v1/product-types?listType=paywall');
});

it('fetches the widget key when it is not configured', function (): void {
    Http::fake(['*/v1/widget-key' => Http::response('"fetched-widget-key"')]);

    $client = Comfino::connection('second');

    expect($client->widgetKey())->toBe('fetched-widget-key')
        ->and($client->widgetKey())->toBe('fetched-widget-key');

    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => $request->hasHeader('Api-Key', 'second-api-key'));
});

it('uses the production host outside the sandbox', function (): void {
    Http::fake(['api-ecommerce.comfino.pl/*' => Http::response('false')]);

    expect(Comfino::using(['api_key' => 'live-key', 'sandbox' => false])->isAccountActive())->toBeFalse();

    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://api-ecommerce.comfino.pl/v1/user/is-active');
});

it('rejects non JSON success responses', function (): void {
    Http::fake(['*' => Http::response('<html>maintenance</html>', 200)]);

    Comfino::financialProducts(100000);
})->throws(ApiException::class, 'the body is not JSON');
