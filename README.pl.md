# Laravel Comfino

[![tests](https://github.com/beziworld/laravel-comfino/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-comfino/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-comfino.svg)](https://packagist.org/packages/beziworld/laravel-comfino)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-comfino.svg)](LICENSE.md)

Raty, „kup teraz, zapłać później”, leasing i finansowanie firm [ComfinoPay](https://comfino.pl) w Laravelu, na
bazie REST API ComfinoPay:

- produkty finansowe (oferty z wariantami rat i RRSO) dla kwoty lub koszyka, parametry najlepszej oferty
- wnioski kredytowe (`POST /v1/orders`) z adresem przekierowania `applicationUrl`, podpisywane nagłówkiem
  `Comfino-Order-Signature` jak w oficjalnym kliencie, symulacja (sama walidacja), odczyt statusu i anulowanie
- trasa notyfikacji statusu (`PUT`, z zapasowym `POST`) z weryfikacją `CR-Signature` (SHA3-256), opcjonalną białą
  listą IP i deduplikacją oraz zdarzeniami Laravela
- widget na stronie produktu: konfiguracja, skrypt CDN właściwego środowiska i bezpiecznie escapowany fragment
  `<script>`; klucz uwierzytelniający paywalla
- typy produktów, typy widgetów, kredytodawcy, status aktywacji konta i klucz widgetu
- typowane obiekty koszyka, klienta i ofert, kwoty w groszach z pomocnikami dziesiętnymi
- wiele sklepów: nazwane połączenia, dane dostępowe w locie przez `using()` i resolver dla notyfikacji tenantów
- sandbox (`api-ecommerce.craty.pl`) i produkcja (`api-ecommerce.comfino.pl`), komenda `comfino:status`

[English documentation](README.md)

## Dlaczego ten pakiet

ComfinoPay udostępnia [comfino/php-api-client](https://packagist.org/packages/comfino/php-api-client) (warstwę
protokołu PSR-18) i [comfino/php-sdk](https://packagist.org/packages/comfino/php-sdk) (procedury backendowe dla
wtyczek sklepowych). Żadna z nich nie integruje się z Laravelem: wymagają implementacji PSR-18/PSR-17, interfejsów
zaimplementowanych na własnych encjach zamówień i osobnej obsługi webhooków. W chwili pisania na Packagist nie było
pakietu ComfinoPay dla Laravela. Ten pakiet korzysta z tego samego REST API przez klienta HTTP Laravela
(`Http::fake` działa), dostarcza gotowe obiekty danych, trasę notyfikacji ze zdarzeniami, wiele sklepów i pomocniki
widgetu.

## Instalacja

```bash
composer require beziworld/laravel-comfino
php artisan vendor:publish --tag=comfino-config   # opcjonalnie
```

```dotenv
COMFINO_API_KEY=...            # tajny Api-Key; weryfikuje też CR-Signature notyfikacji
COMFINO_WIDGET_KEY=...         # publiczny klucz widgetu; pobierany z API, gdy pusty
COMFINO_SANDBOX=true           # api-ecommerce.craty.pl; false = api-ecommerce.comfino.pl
COMFINO_RETURN_URL=https://sklep.example.com/comfino/powrot
```

Klucze sandboxa i produkcji nie są wymienne. Notyfikacje trafiają na trasę pakietu
`PUT|POST /comfino/notification/{connection}`; jej adres jest wysyłany jako `notifyUrl` automatycznie. Klucz
sprawdzisz poleceniem:

```bash
php artisan comfino:status --amount=1500
```

## Oferty

Wszystkie kwoty to liczby całkowite w groszach (`Amount::toMinor('1499.99')` daje `149999`).

```php
use Beziworld\Comfino\Data\ProductQuery;
use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Facades\Comfino;

foreach (Comfino::financialProducts(149999) as $product) {
    $product->name;                                   // "Raty 0%"
    $product->type;                                   // LoanType::InstallmentsZeroPercent (null dla nieznanych)
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

Comfino::bestOffer(149999);               // najniższa rata: liczba rat, rata, kwota do spłaty
Comfino::financialProductsForCart($cart); // oferty zależne od koszyka, np. leasing
```

ComfinoPay zaleca 5-sekundowy timeout połączenia dla ofert na stronie produktu (`COMFINO_CONNECT_TIMEOUT`);
przechwyć `ApiException` i po cichu ukryj ofertę, gdy zapytanie się nie powiedzie.

## Wniosek kredytowy

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
    CartItem::fee('Dopłata za płatność', 500),        // kategoria ADDITIONAL_FEE
    CartItem::discount('Kupon WIOSNA', 1000),         // kategoria DISCOUNT, ujemna cena
], deliveryCost: 1230);                               // totalAmount = pozycje + dostawa

$created = Comfino::createOrder(new OrderRequest(
    orderId: $order->number,                          // maks. 36 znaków, wraca jako externalId
    cart: $cart,
    customer: new Customer(
        firstName: 'Jan',
        lastName: 'Kowalski',
        email: 'jan@example.com',
        phoneNumber: '+48 600 700 800',               // normalizowany do 9 cyfr
        ip: $request->ip(),
        address: new Address('Różana', '1', null, '10-899', 'Warszawa'),
    ),
    loanTerm: 10,                                      // opcjonalny wybór wstępny
    loanType: LoanType::InstallmentsZeroPercent,
));

return redirect()->away($created->applicationUrl);
```

`Cart` odrzuca sumę różną od sumy pozycji i dostawy, na którą ComfinoPay odpowiada HTTP 400.
`Comfino::validateOrder($request)` wysyła to samo body w trybie symulacji. Wnioski nigdy nie są automatycznie
ponawiane.

Po powrocie klienta na `returnUrl` decyduj na podstawie statusów końcowych: `OrderStatus::isSuccessful()`
(ACCEPTED, WAITING_FOR_PAYMENT, PAID), `isFailed()` (REJECTED, CANCELLED_BY_SHOP, RESIGN, CANCELLED), `isPending()`.

```php
$order = Comfino::order('ZAM-001');   // GET /v1/orders/{id}: status, createdAt, kwota i okres kredytu, surowe dane
Comfino::cancelOrder('ZAM-001');      // PUT /v1/orders/{id}/cancel, zwraca OrderStatus::CancelledByShop
```

### Notyfikacje i zdarzenia

ComfinoPay wysyła `{"status", "externalId", "changedAt", "paymentMethod", "productType"}` i powtarza notyfikację
aż do otrzymania HTTP 200. Trasa weryfikuje `CR-Signature = sha3-256(apiKey + surowe body)` i wysyła zdarzenia:

| Zdarzenie | Statusy |
|---|---|
| `OrderStatusChanged` | każda autentyczna notyfikacja |
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

Listenery muszą być idempotentne. Podrobione notyfikacje i nadawcy spoza `comfino.notifications.allowed_ips` (gdy
lista jest ustawiona; udokumentowane adresy ComfinoPay są w pliku konfiguracyjnym) dostają HTTP 403, błędne body
HTTP 400. `COMFINO_DEDUPLICATE_TTL=600` potwierdza identyczną powtórną dostawę bez ponownego wysyłania zdarzeń.
Dla własnej trasy użyj `Comfino::parseNotification($rawBody, $request->header('CR-Signature'))`.

## Widget

```blade
{!! Comfino::widget()->snippet([
    'priceSelector' => 'span[itemprop=price]',
    'widgetTargetSelector' => 'div.product-actions',
    'type' => \Beziworld\Comfino\Enums\WidgetType::Standard,
    'offerTypes' => [\Beziworld\Comfino\Enums\LoanType::InstallmentsZeroPercent],
], nonce: Vite::cspNonce()) !!}
```

`widget()->config([...])` zwraca opcje `ComfinoWidgetFrontend.init()` dla własnego skryptu, a
`widget()->scriptUrl()` skrypt CDN środowiska (`widget.craty.pl` albo `widget.comfino.pl`). Wartości domyślne
pochodzą z `comfino.widget`. `Comfino::paywallAuthKey()` zwraca 15-minutowy token iframe paywalla w kasie.

## Wiele sklepów

```php
Comfino::connection('second-shop')->financialProducts(100000);

$client = Comfino::using(['api_key' => $tenant->comfino_key, 'name' => $tenant->slug]);

// Notyfikacje połączeń w locie trafiają na /comfino/notification/{slug}:
Comfino::resolveConnectionsUsing(fn (string $name): ?array => Tenant::where('slug', $name)->first()?->comfinoConfig());
```

## Błędy

| Wyjątek | Kiedy |
|---|---|
| `InvalidConfiguration` | brak `COMFINO_API_KEY`, nieznane połączenie, brak adresu powrotu |
| `InvalidOrder` | dane łamią udokumentowane ograniczenie (suma koszyka, długość orderId, telefon, e-mail, IP) |
| `ApiException` | błąd HTTP (`httpStatus()`, `errors()`, `retryAfter()`, `isUnauthorized()`, `isValidationError()`, `isNotFound()`, `isRateLimited()`) lub brak połączenia |
| `InvalidNotification` | brak lub błędny `CR-Signature`, niedozwolony nadawca, błędne body |

Wszystkie dziedziczą po `ComfinoException`. Odczyty są ponawiane przy błędach połączenia i HTTP 5xx
(`COMFINO_RETRIES`); HTTP 429 nigdy nie jest ponawiany automatycznie.

## Rozwój

```bash
composer install
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pest
```

Kształty żądań i odpowiedzi wynikają z [oficjalnej dokumentacji](https://developers.comfino.pl/docs/api) i
oficjalnego klienta. Odpowiedź `GET /v1/orders/{id}` nie jest opisana w publicznej dokumentacji; pakiet czyta ją
według układu z oficjalnego klienta i zachowuje w `raw`.

## Autor

Pakiet tworzy i rozwija **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu), [biuro@beziworld.eu](mailto:biuro@beziworld.eu).
Zgłoszenia: [GitHub issues](https://github.com/beziworld/laravel-comfino/issues).
Potrzebujesz integracji? Zapraszam do kontaktu przez [beziworld.eu](https://beziworld.eu).

## Licencja

MIT. Zobacz [LICENSE.md](LICENSE.md). Pakiet nie jest powiązany z ComfinoPay sp. z o.o.
