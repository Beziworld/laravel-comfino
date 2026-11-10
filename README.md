# Laravel Comfino

[![tests](https://github.com/beziworld/laravel-comfino/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-comfino/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-comfino.svg)](https://packagist.org/packages/beziworld/laravel-comfino)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-comfino.svg)](LICENSE.md)

[ComfinoPay](https://comfino.pl) installments, "buy now, pay later", leasing and company financing for Laravel,
built on the ComfinoPay REST API:

- financial products (offers with instalment variants and RRSO) for an amount or for a cart, best possible offer
- loan applications (`POST /v1/orders`) with the redirect `applicationUrl`, signed with `Comfino-Order-Signature`
  like the official client, simulation (validation only), status read and cancellation
- status notification route (`PUT`, with the `POST` fallback) with `CR-Signature` (SHA3-256) verification, optional
  IP allow list and deduplication, and Laravel events
- product page widget: configuration, CDN script of the environment and an escaped `<script>` snippet; paywall
  authentication key
- product types, widget types, creditors, account activation and widget key
- typed cart, customer and offer objects, amounts in grosz with decimal helpers
- many shops: named connections, runtime credentials with `using()` and a resolver for tenant notifications
- sandbox (`api-ecommerce.craty.pl`) and production (`api-ecommerce.comfino.pl`), `comfino:status` artisan command

[Polska wersja dokumentacji](README.pl.md)

## Why this package

ComfinoPay publishes [comfino/php-api-client](https://packagist.org/packages/comfino/php-api-client) (a PSR-18
protocol layer) and [comfino/php-sdk](https://packagist.org/packages/comfino/php-sdk) (backend routines for shop
plugins). Neither integrates with Laravel: they expect PSR-18/PSR-17 implementations, interfaces implemented over
your own order entities and a webhook stack of their own. At the time of writing there was no Laravel package for
ComfinoPay on Packagist. This package talks to the same REST API through the Laravel HTTP client (`Http::fake`
works), ships ready data objects, a notification route with events, multi-shop connections and widget helpers.

## Installation

```bash
composer require beziworld/laravel-comfino
php artisan vendor:publish --tag=comfino-config   # optional
```

```dotenv
COMFINO_API_KEY=...            # secret Api-Key; also verifies CR-Signature of notifications
COMFINO_WIDGET_KEY=...         # public widget key; fetched from the API when empty
COMFINO_SANDBOX=true           # api-ecommerce.craty.pl; false = api-ecommerce.comfino.pl
COMFINO_RETURN_URL=https://shop.example.com/comfino/return
```

Sandbox and production keys are not interchangeable. Notifications go to the package route
`PUT|POST /comfino/notification/{connection}`; the URL of the connection is sent as `notifyUrl` automatically.
Check the key with:

```bash
php artisan comfino:status --amount=1500
```

## Offers

All amounts are integers in grosz (`Amount::toMinor('1499.99')` gives `149999`).

```php
use Beziworld\Comfino\Data\ProductQuery;
use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Facades\Comfino;

foreach (Comfino::financialProducts(149999) as $product) {
    $product->name;                                   // "Raty 0%"
    $product->type;                                   // LoanType::InstallmentsZeroPercent (null when unknown)
    $product->defaultOption->instalment->decimal();   // "150.00"
    $product->defaultOption->rrsoPercent();           // "0.00"
    $product->terms();                                // [3, 6, 10]
    $product->option(6)?->toPay;                      // Money
}

Comfino::financialProducts(new ProductQuery(
    loanAmount: 149999,
    loanTerm: 12,
    productTypes: [LoanType::ConvenientInstallments, LoanType::PayLater],
));

Comfino::bestOffer(149999);               // the lowest instalment: term, instalment, toPay
Comfino::financialProductsForCart($cart); // offers that depend on the cart, e.g. leasing
```

ComfinoPay recommends a 5 second connection timeout for offers on product pages (`COMFINO_CONNECT_TIMEOUT`); catch
`ApiException` and hide the offer silently when it fails.

## Loan application

```php
use Beziworld\Comfino\Data\Address;
use Beziworld\Comfino\Data\Cart;
use Beziworld\Comfino\Data\CartItem;
use Beziworld\Comfino\Data\Customer;
use Beziworld\Comfino\Data\OrderRequest;
use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Support\Amount;

$cart = Cart::make([
    new CartItem('Lenovo Ideapad 120S', Amount::toMinor('2460.00'), quantity: 1, ean: '9002490100070', externalId: 'SKU-123'),
    CartItem::fee('Payment surcharge', 500),        // category ADDITIONAL_FEE
    CartItem::discount('Coupon SPRING', 1000),       // category DISCOUNT, negative price
], deliveryCost: 1230);                              // totalAmount = positions + delivery

$created = Comfino::createOrder(new OrderRequest(
    orderId: $order->number,                         // max. 36 characters, returned as externalId
    cart: $cart,
    customer: new Customer(
        firstName: 'Jan',
        lastName: 'Kowalski',
        email: 'jan@example.com',
        phoneNumber: '+48 600 700 800',              // normalised to 9 digits
        ip: $request->ip(),
        address: new Address('Różana', '1', null, '10-899', 'Warszawa'),
    ),
    loanTerm: 10,                                     // optional preselection
    loanType: LoanType::InstallmentsZeroPercent,
));

return redirect()->away($created->applicationUrl);
```

`Cart` refuses a total that differs from the sum of positions and delivery, which ComfinoPay answers with HTTP 400.
`Comfino::validateOrder($request)` sends the same body in simulation mode. Loan applications are never retried
automatically.

On return to `returnUrl`, decide from the final statuses: `OrderStatus::isSuccessful()` (ACCEPTED,
WAITING_FOR_PAYMENT, PAID), `isFailed()` (REJECTED, CANCELLED_BY_SHOP, RESIGN, CANCELLED), `isPending()`.

```php
$order = Comfino::order('ZAM-001');   // GET /v1/orders/{id}: status, createdAt, loan amount and term, raw payload
Comfino::cancelOrder('ZAM-001');      // PUT /v1/orders/{id}/cancel, returns OrderStatus::CancelledByShop
```

### Notifications and events

ComfinoPay sends `{"status", "externalId", "changedAt", "paymentMethod", "productType"}` and repeats the
notification until it gets HTTP 200. The route verifies `CR-Signature = sha3-256(apiKey + rawBody)` and dispatches:

| Event | Statuses |
|---|---|
| `OrderStatusChanged` | every authentic notification |
| `OrderAccepted` | ACCEPTED, WAITING_FOR_PAYMENT |
| `OrderPaid` | PAID |
| `OrderRejected` | REJECTED |
| `OrderCancelled` | CANCELLED_BY_SHOP, CANCELLED, RESIGN |

```php
use Beziworld\Comfino\Events\OrderPaid;

Event::listen(function (OrderPaid $event): void {
    Order::where('number', $event->notification->externalId)->first()?->markAsPaid();
});
```

Listeners must be idempotent. Forged notifications and senders outside `comfino.notifications.allowed_ips` (when
set; the documented ComfinoPay addresses are listed in the config file) get HTTP 403, malformed bodies HTTP 400.
`COMFINO_DEDUPLICATE_TTL=600` acknowledges an identical repeated delivery without dispatching the events again.
For a custom route use `Comfino::parseNotification($rawBody, $request->header('CR-Signature'))`.

## Widget

```blade
{!! Comfino::widget()->snippet([
    'priceSelector' => 'span[itemprop=price]',
    'widgetTargetSelector' => 'div.product-actions',
    'type' => \Beziworld\Comfino\Enums\WidgetType::Standard,
    'offerTypes' => [\Beziworld\Comfino\Enums\LoanType::InstallmentsZeroPercent],
], nonce: Vite::cspNonce()) !!}
```

`widget()->config([...])` returns the `ComfinoWidgetFrontend.init()` options for your own script and
`widget()->scriptUrl()` the CDN script of the environment (`widget.craty.pl` or `widget.comfino.pl`). Defaults come
from `comfino.widget`. `Comfino::paywallAuthKey()` returns the 15 minute token of the checkout paywall iframe.

## Many shops

```php
Comfino::connection('second-shop')->financialProducts(100000);

$client = Comfino::using(['api_key' => $tenant->comfino_key, 'name' => $tenant->slug]);

// Notifications of runtime connections arrive at /comfino/notification/{slug}:
Comfino::resolveConnectionsUsing(fn (string $name): ?array => Tenant::where('slug', $name)->first()?->comfinoConfig());
```

## Errors

| Exception | When |
|---|---|
| `InvalidConfiguration` | missing `COMFINO_API_KEY`, unknown connection, no return URL |
| `InvalidOrder` | data violates a documented constraint (cart total, orderId length, phone, e-mail, IP) |
| `ApiException` | HTTP error (`httpStatus()`, `errors()`, `retryAfter()`, `isUnauthorized()`, `isValidationError()`, `isNotFound()`, `isRateLimited()`) or connection failure |
| `InvalidNotification` | missing or invalid `CR-Signature`, disallowed sender, malformed body |

All extend `ComfinoException`. Reads are retried on connection errors and HTTP 5xx (`COMFINO_RETRIES`); HTTP 429
is never retried automatically.

## Development

```bash
composer install
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pest
```

Request and response shapes follow the [official documentation](https://developers.comfino.pl/docs/api) and the
official client. The response of `GET /v1/orders/{id}` is not described in the public documentation; it is parsed
with the layout used by the official client and kept in `raw`.

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues: [GitHub issues](https://github.com/beziworld/laravel-comfino/issues)
- Security vulnerabilities: see [SECURITY.md](SECURITY.md)

Need an integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

[laravel-paypo](https://github.com/beziworld/laravel-paypo) ·
[laravel-payu](https://github.com/beziworld/laravel-payu) ·
[laravel-paynow](https://github.com/beziworld/laravel-paynow) ·
[laravel-przelewy24](https://github.com/beziworld/laravel-przelewy24) ·
[laravel-tpay](https://github.com/beziworld/laravel-tpay)

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with ComfinoPay sp. z o.o.
