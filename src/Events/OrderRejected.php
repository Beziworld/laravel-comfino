<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Events;

use Beziworld\Comfino\Data\Notification;

/**
 * Dispatched when the lender refused financing (REJECTED).
 */
final readonly class OrderRejected
{
    public function __construct(
        public Notification $notification,
        public ?string $connection = null,
    ) {}
}
