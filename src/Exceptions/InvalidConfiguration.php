<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Exceptions;

/**
 * Raised when the package configuration is incomplete or refers to an unknown connection.
 */
final class InvalidConfiguration extends ComfinoException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('ComfinoPay "%s" is not configured. Set %s in the environment.', $key, $env));
    }

    public static function unknownConnection(string $name): self
    {
        return new self(sprintf('ComfinoPay connection "%s" is not defined in config/comfino.php.', $name));
    }

    public static function missingUrl(string $key): self
    {
        return new self(sprintf('ComfinoPay loan application requires "%s": pass it in the request or set it in config/comfino.php.', $key));
    }
}
