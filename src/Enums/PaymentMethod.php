<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Enums;

/**
 * Payment method chosen by the customer, as reported in status notifications.
 *
 * A null payment method in the notification means that financing has not been granted yet.
 */
enum PaymentMethod: string
{
    case Comfino = 'COMFINO';
    case Blik = 'BLIK';
    case Card = 'CARD';
}
