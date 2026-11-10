<?php

declare(strict_types=1);

use Beziworld\Comfino\Data\Address;
use Beziworld\Comfino\Data\Cart;
use Beziworld\Comfino\Data\CartItem;
use Beziworld\Comfino\Data\Customer;
use Beziworld\Comfino\Data\Notification;
use Beziworld\Comfino\Data\OrderRequest;
use Beziworld\Comfino\Data\ProductQuery;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Exceptions\InvalidOrder;

it('builds the cart of the documented example', function (): void {
    $cart = new Cart(
        items: [
            new CartItem('Lenovo Ideapad 120S-14IAP', 246000, netPrice: 200000, vatRate: 23, vatAmount: 46000, photoUrl: '01e5f8', ean: '9002490100070', externalId: '123', category: 'productCategory'),
            new CartItem('Lenovo Ideapad 120S-14IAP', 196677, netPrice: 159900, vatRate: 23, vatAmount: 36777),
        ],
        totalAmount: 443907,
        deliveryCost: 1230,
        deliveryNetCost: 1000,
        deliveryCostVatRate: 23,
        deliveryCostVatAmount: 230,
    );

    expect($cart->toArray())->toMatchArray([
        'totalAmount' => 443907,
        'deliveryCost' => 1230,
        'deliveryNetCost' => 1000,
        'deliveryCostVatRate' => 23,
        'deliveryCostVatAmount' => 230,
    ])->and($cart->toArray()['products'][0])->toBe([
        'name' => 'Lenovo Ideapad 120S-14IAP',
        'quantity' => 1,
        'price' => 246000,
        'photoUrl' => '01e5f8',
        'ean' => '9002490100070',
        'externalId' => '123',
        'category' => 'productCategory',
        'netPrice' => 200000,
        'vatRate' => 23,
        'vatAmount' => 46000,
    ]);
});

it('computes totals with fees and discounts', function (): void {
    $cart = Cart::make([
        new CartItem('Laptop', 10000),
        new CartItem('Mouse', 10000, quantity: 2),
        CartItem::fee('Dopłata rodzaj płatności', 5000),
        CartItem::discount('Rabat', 5000),
    ], deliveryCost: 1000);

    expect($cart->totalAmount)->toBe(31000)
        ->and($cart->items[3]->price)->toBe(-5000)
        ->and($cart->items[3]->category)->toBe('DISCOUNT')
        ->and($cart->items[2]->category)->toBe('ADDITIONAL_FEE');
});

it('rejects carts whose total does not match', function (): void {
    new Cart([new CartItem('Laptop', 10000)], totalAmount: 12000);
})->throws(InvalidOrder::class, 'differs from the sum of positions');

it('rejects empty carts and invalid positions', function (): void {
    expect(fn() => Cart::make([]))->toThrow(InvalidOrder::class, 'at least one product')
        ->and(fn() => new CartItem(' ', 100))->toThrow(InvalidOrder::class, 'name')
        ->and(fn() => new CartItem('Laptop', 100, quantity: 0))->toThrow(InvalidOrder::class, 'quantity');
});

it('normalises customer data and validates documented constraints', function (): void {
    $customer = new Customer('Jan', 'Kowalski', 'jan@example.com', '0048 600-700-800', '2001:db8::1', taxId: '525-000-10-09', regular: true, address: new Address('Różana', '1', null, '10-899', 'Warszawa', 'pl'));

    expect($customer->toArray())->toBe([
        'firstName' => 'Jan',
        'lastName' => 'Kowalski',
        'email' => 'jan@example.com',
        'phoneNumber' => '600700800',
        'taxId' => '5250001009',
        'ip' => '2001:db8::1',
        'regular' => true,
        'address' => ['street' => 'Różana', 'buildingNumber' => '1', 'postalCode' => '10-899', 'city' => 'Warszawa', 'countryCode' => 'PL'],
    ]);

    expect(fn() => new Customer('J', 'Kowalski', 'jan@example.com', '600700800', '1.2.3.4'))->toThrow(InvalidOrder::class, 'firstName')
        ->and(fn() => new Customer('Jan', 'Kowalski', 'not-an-email', '600700800', '1.2.3.4'))->toThrow(InvalidOrder::class, 'email')
        ->and(fn() => new Customer('Jan', 'Kowalski', 'jan@example.com', '600 700', '1.2.3.4'))->toThrow(InvalidOrder::class, '9 digits')
        ->and(fn() => new Customer('Jan', 'Kowalski', 'jan@example.com', '600700800', 'localhost'))->toThrow(InvalidOrder::class, 'ip');
});

it('splits a full name as ComfinoPay recommends', function (): void {
    $customer = Customer::fromFullName('Jan Maria  Rokita', 'jan@example.com', '600700800', '1.2.3.4');

    expect($customer->firstName)->toBe('Jan')->and($customer->lastName)->toBe('Maria  Rokita');
});

it('validates order identifiers and optional fields', function (): void {
    $cart = Cart::make([new CartItem('Laptop', 10000)]);
    $customer = new Customer('Jan', 'Kowalski', 'jan@example.com', '600700800', '1.2.3.4');

    expect(fn() => new OrderRequest(str_repeat('A', 37), $cart, $customer))->toThrow(InvalidOrder::class, '36 characters')
        ->and(fn() => new OrderRequest('A', $cart, $customer, accountNumber: 'PL61109010140000071219812874'))->toThrow(InvalidOrder::class, '26 digits')
        ->and(fn() => new OrderRequest('A', $cart, $customer, transferTitle: str_repeat('x', 141)))->toThrow(InvalidOrder::class, '140')
        ->and(fn() => (new OrderRequest('A', $cart, $customer, returnUrl: 'https://e.pl/' . str_repeat('x', 250)))->toArray())->toThrow(InvalidOrder::class, '255')
        ->and((new OrderRequest('A', $cart, $customer, extra: ['custom' => 1]))->toArray('https://shop.pl/r')['custom'])->toBe(1);
});

it('rejects non positive offer amounts', function (): void {
    new ProductQuery(0);
})->throws(InvalidOrder::class, 'loanAmount');

it('maps statuses to outcomes', function (): void {
    expect(OrderStatus::Paid->isSuccessful())->toBeTrue()
        ->and(OrderStatus::WaitingForPayment->isSuccessful())->toBeTrue()
        ->and(OrderStatus::CancelledByShop->isFailed())->toBeTrue()
        ->and(OrderStatus::PreAccepted->isPending())->toBeTrue()
        ->and(OrderStatus::Created->label())->toBe('Application created by the shop');
});

it('keeps unknown notification values as strings', function (): void {
    $notification = Notification::fromArray(['status' => 'NEW_STATUS', 'externalId' => 42, 'changedAt' => 1762770000123, 'paymentMethod' => 'APPLE_PAY', 'productType' => null]);

    expect($notification->status)->toBeNull()
        ->and($notification->statusValue)->toBe('NEW_STATUS')
        ->and($notification->externalId)->toBe('42')
        ->and($notification->paymentMethod)->toBeNull()
        ->and($notification->paymentMethodValue)->toBe('APPLE_PAY')
        ->and($notification->changedAt?->getTimestampMs())->toBe(1762770000123)
        ->and($notification->isSuccessful())->toBeFalse()
        ->and($notification->isFailed())->toBeFalse();
});
