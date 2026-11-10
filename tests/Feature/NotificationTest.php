<?php

declare(strict_types=1);

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Enums\PaymentMethod;
use Beziworld\Comfino\Events\OrderAccepted;
use Beziworld\Comfino\Events\OrderCancelled;
use Beziworld\Comfino\Events\OrderPaid;
use Beziworld\Comfino\Events\OrderRejected;
use Beziworld\Comfino\Events\OrderStatusChanged;
use Beziworld\Comfino\Exceptions\InvalidNotification;
use Beziworld\Comfino\Facades\Comfino;
use Beziworld\Comfino\Support\Signature;
use Beziworld\Comfino\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * Sends a raw notification body to the package route, signed with the given key.
 */
function sendComfinoNotification(
    TestCase $test,
    string $body,
    ?string $key = TestCase::API_KEY,
    string $path = '/comfino/notification/default',
    string $method = 'PUT',
    string $header = 'HTTP_CR_SIGNATURE',
    array $server = [],
): TestResponse {
    $headers = ['CONTENT_TYPE' => 'application/json'] + $server;

    if ($key !== null) {
        $headers[$header] = Signature::compute($body, $key);
    }

    return $test->call($method, $path, [], [], [], $headers, $body);
}

function fakeComfinoEvents(): void
{
    Event::fake([OrderStatusChanged::class, OrderAccepted::class, OrderPaid::class, OrderRejected::class, OrderCancelled::class]);
}

function notificationWithStatus(string $status): string
{
    return json_encode(['status' => $status] + comfinoFixture('notification'), JSON_UNESCAPED_SLASHES);
}

it('dispatches events for an authentic PUT notification', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'))->assertOk()->assertContent('');

    Event::assertDispatched(OrderStatusChanged::class, fn(OrderStatusChanged $event): bool => $event->connection === 'default'
        && $event->notification->status === OrderStatus::Accepted
        && $event->notification->externalId === 'ZAM-001'
        && $event->notification->paymentMethod === PaymentMethod::Comfino
        && $event->notification->productType === LoanType::InstallmentsZeroPercent
        && $event->notification->changedAt?->getTimestamp() === 1762770000
        && $event->notification->changedAt->getTimezone()->getName() === 'Europe/Warsaw'
        && $event->notification->isSuccessful());
    Event::assertDispatched(OrderAccepted::class);
    Event::assertNotDispatched(OrderPaid::class);
});

it('accepts the POST fallback and the default connection route', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'), path: '/comfino/notification', method: 'POST')->assertOk();

    Event::assertDispatched(OrderStatusChanged::class, fn(OrderStatusChanged $event): bool => $event->connection === 'default');
});

it('accepts the X-CR-Signature header', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'), header: 'HTTP_X_CR_SIGNATURE')->assertOk();

    Event::assertDispatched(OrderAccepted::class);
});

it('dispatches the event matching each status', function (string $status, ?string $event): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, notificationWithStatus($status))->assertOk();

    Event::assertDispatched(OrderStatusChanged::class);

    foreach ([OrderAccepted::class, OrderPaid::class, OrderRejected::class, OrderCancelled::class] as $class) {
        $class === $event ? Event::assertDispatched($class) : Event::assertNotDispatched($class);
    }
})->with([
    'waiting for payment' => ['WAITING_FOR_PAYMENT', OrderAccepted::class],
    'paid' => ['PAID', OrderPaid::class],
    'rejected' => ['REJECTED', OrderRejected::class],
    'cancelled by shop' => ['CANCELLED_BY_SHOP', OrderCancelled::class],
    'resign' => ['RESIGN', OrderCancelled::class],
    'created' => ['CREATED', null],
    'unknown' => ['SOMETHING_NEW', null],
]);

it('rejects a notification signed with another key', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'), key: 'other-key')->assertForbidden();

    Event::assertNothingDispatched();
});

it('rejects an unsigned notification', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'), key: null)->assertForbidden();

    Event::assertNothingDispatched();
});

it('rejects a body changed after signing', function (): void {
    fakeComfinoEvents();
    $body = comfinoRawFixture('notification');

    $this->call('PUT', '/comfino/notification/default', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CR_SIGNATURE' => Signature::compute($body, TestCase::API_KEY),
    ], str_replace('ACCEPTED', 'PAID', $body))->assertForbidden();

    Event::assertNothingDispatched();
});

it('verifies with the key of the connection in the URL', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'), key: 'second-api-key', path: '/comfino/notification/second')->assertOk();
    sendComfinoNotification($this, comfinoRawFixture('notification'), path: '/comfino/notification/second')->assertForbidden();

    Event::assertDispatched(OrderStatusChanged::class, fn(OrderStatusChanged $event): bool => $event->connection === 'second');
});

it('rejects unknown connections', function (): void {
    sendComfinoNotification($this, comfinoRawFixture('notification'), path: '/comfino/notification/unknown')->assertForbidden();
});

it('resolves runtime connections for notifications', function (): void {
    fakeComfinoEvents();
    Comfino::resolveConnectionsUsing(fn(string $name): ?array => $name === 'tenant-7' ? ['api_key' => 'tenant-key'] : null);

    sendComfinoNotification($this, comfinoRawFixture('notification'), key: 'tenant-key', path: '/comfino/notification/tenant-7')->assertOk();

    Event::assertDispatched(OrderStatusChanged::class, fn(OrderStatusChanged $event): bool => $event->connection === 'tenant-7');
});

it('answers malformed bodies with HTTP 400', function (string $body): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, $body)->assertStatus(400);

    Event::assertNothingDispatched();
})->with([
    'not json' => ['status=PAID'],
    'list' => ['["PAID"]'],
    'no status' => ['{"externalId":"ZAM-001"}'],
    'no external id' => ['{"status":"PAID"}'],
]);

it('restricts senders when allowed IPs are configured', function (): void {
    fakeComfinoEvents();
    config()->set('comfino.notifications.allowed_ips', ['94.152.189.231', '35.187.24.192/32']);

    sendComfinoNotification($this, comfinoRawFixture('notification'), server: ['REMOTE_ADDR' => '203.0.113.9'])->assertForbidden();
    sendComfinoNotification($this, comfinoRawFixture('notification'), server: ['REMOTE_ADDR' => '35.187.24.192'])->assertOk();

    Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
});

it('acknowledges a repeated notification without dispatching again when deduplication is on', function (): void {
    fakeComfinoEvents();
    config()->set('comfino.notifications.deduplicate_ttl', 600);

    sendComfinoNotification($this, comfinoRawFixture('notification'))->assertOk();
    sendComfinoNotification($this, comfinoRawFixture('notification'))->assertOk();
    sendComfinoNotification($this, notificationWithStatus('PAID'))->assertOk();

    Event::assertDispatchedTimes(OrderStatusChanged::class, 2);
});

it('dispatches every delivery without deduplication', function (): void {
    fakeComfinoEvents();

    sendComfinoNotification($this, comfinoRawFixture('notification'))->assertOk();
    sendComfinoNotification($this, comfinoRawFixture('notification'))->assertOk();

    Event::assertDispatchedTimes(OrderStatusChanged::class, 2);
});

it('can skip signature verification explicitly', function (): void {
    fakeComfinoEvents();
    config()->set('comfino.notifications.verify_signature', false);

    sendComfinoNotification($this, comfinoRawFixture('notification'), key: null)->assertOk();

    Event::assertDispatched(OrderStatusChanged::class);
});

it('parses and verifies notifications outside the route', function (): void {
    $body = comfinoRawFixture('notification');
    $signature = hash('sha3-256', TestCase::API_KEY . $body);

    expect(Comfino::verifySignature($body, $signature))->toBeTrue()
        ->and(Comfino::verifySignature($body, strtoupper($signature)))->toBeTrue()
        ->and(Comfino::verifySignature($body . ' ', $signature))->toBeFalse()
        ->and(Comfino::parseNotification($body, $signature)->statusValue)->toBe('ACCEPTED');

    expect(fn() => Comfino::parseNotification($body, 'deadbeef'))->toThrow(InvalidNotification::class, 'does not match');
});

it('registers a named notification route per connection', function (): void {
    expect(route('comfino.notification', ['connection' => 'default']))->toBe('https://shop.example.com/comfino/notification/default');
});
