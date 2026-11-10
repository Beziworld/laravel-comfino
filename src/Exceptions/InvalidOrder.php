<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Exceptions;

/**
 * Raised before sending when loan application or offer query data violates documented ComfinoPay constraints.
 */
final class InvalidOrder extends ComfinoException
{
    public static function because(string $reason): self
    {
        return new self('Invalid ComfinoPay request: ' . $reason . '.');
    }
}
