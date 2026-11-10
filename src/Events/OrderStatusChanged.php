<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Events;

use Beziworld\Comfino\Data\Notification;

/**
 * Dispatched for every authentic status notification.
 *
 * ComfinoPay repeats notifications until it receives HTTP 200 and may send
 * several for one order, so listeners must be idempotent. The more specific
 * events (OrderAccepted, OrderPaid, OrderRejected, OrderCancelled) are
 * dispatched after this one.
 */
final readonly class OrderStatusChanged
{
    /**
     * @param string|null $connection Name of the connection that received the notification.
     */
    public function __construct(
        public Notification $notification,
        public ?string $connection = null,
    ) {}
}
