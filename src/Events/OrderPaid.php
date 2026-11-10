<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Events;

use Beziworld\Comfino\Data\Notification;

/**
 * Dispatched when the lender transferred the money to the shop (PAID).
 */
final readonly class OrderPaid
{
    public function __construct(
        public Notification $notification,
        public ?string $connection = null,
    ) {}
}
