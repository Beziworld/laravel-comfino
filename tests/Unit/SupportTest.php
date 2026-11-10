<?php

declare(strict_types=1);

use Beziworld\Comfino\Exceptions\ApiException;
use Beziworld\Comfino\Exceptions\InvalidNotification;
use Beziworld\Comfino\Support\Amount;
use Beziworld\Comfino\Support\Signature;

it('converts decimal amounts to grosz and back', function (int|float|string $input, int $minor): void {
    expect(Amount::toMinor($input))->toBe($minor);
})->with([
    'string' => ['1499.99', 149999],
    'comma and spaces' => ['1 499,9', 149990],
    'float' => [19.99, 1999],
    'whole units' => [15, 1500],
    'negative' => ['-50', -5000],
]);

it('formats grosz as decimals', function (): void {
    expect(Amount::toDecimal(149999))->toBe('1499.99')
        ->and(Amount::toDecimal(5))->toBe('0.05')
        ->and(Amount::toDecimal(-5000))->toBe('-50.00');
});

it('rejects invalid amounts', function (): void {
    Amount::toMinor('12.345');
})->throws(InvalidArgumentException::class);

it('computes CR-Signature as sha3-256 of the key and the raw body', function (): void {
    $body = '{"status":"PAID","externalId":"ZAM-001"}';

    expect(Signature::compute($body, 'key'))->toBe(hash('sha3-256', 'key' . $body))
        ->and(strlen(Signature::compute($body, 'key')))->toBe(64);

    Signature::verify($body, Signature::compute($body, 'key'), 'key');
});

it('fails closed on empty signatures and keys', function (): void {
    expect(fn() => Signature::verify('{}', '', 'key'))->toThrow(InvalidNotification::class, 'no CR-Signature')
        ->and(fn() => Signature::verify('{}', hash('sha3-256', '{}'), ''))->toThrow(InvalidNotification::class, 'does not match');
});

it('signs loan applications like the official client', function (): void {
    $cart = ['products' => [['name' => 'A', 'quantity' => 1, 'price' => 100]], 'totalAmount' => 100];
    $customer = ['firstName' => 'Jan', 'url' => 'https://e.pl/ż'];
    $headers = Signature::orderHeaders($cart, $customer, 'key');
    $cartHash = hash('sha3-256', json_encode($cart, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    $customerHash = hash('sha3-256', json_encode($customer, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    expect($headers)->toBe([
        'Comfino-Cart-Hash' => $cartHash,
        'Comfino-Customer-Hash' => $customerHash,
        'Comfino-Order-Signature' => hash('sha3-256', $cartHash . $customerHash . 'key'),
    ]);
});

it('parses the documented error body shapes', function (): void {
    expect(ApiException::fromResponse(['message' => 'Authorization failed'], 401)->errors())->toBe(['_' => ['Authorization failed']])
        ->and(ApiException::fromResponse(['orderId' => 'Too long'], 400)->errors())->toBe(['orderId' => ['Too long']])
        ->and(ApiException::fromResponse([], 500)->getMessage())->toBe('ComfinoPay error (HTTP 500): request failed with HTTP status 500')
        ->and(ApiException::fromResponse([], 503)->isServerError())->toBeTrue()
        ->and(ApiException::fromResponse([], 409)->isConflict())->toBeTrue();
});
