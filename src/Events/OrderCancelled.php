<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Events;

use Beziworld\Comfino\Data\Notification;

/**
 * Dispatched when the application was cancelled or withdrawn (CANCELLED_BY_SHOP, CANCELLED or RESIGN).
 */
final readonly class OrderCancelled
{
    public function __construct(
        public Notification $notification,
        public ?string $connection = null,
    ) {}
}
