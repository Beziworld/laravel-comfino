<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Events;

use Beziworld\Comfino\Data\Notification;

/**
 * Dispatched when financing was granted (ACCEPTED or WAITING_FOR_PAYMENT).
 */
final readonly class OrderAccepted
{
    public function __construct(
        public Notification $notification,
        public ?string $connection = null,
    ) {}
}
