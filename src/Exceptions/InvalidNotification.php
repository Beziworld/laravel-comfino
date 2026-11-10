<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Exceptions;

/**
 * Raised when an incoming status notification is not authentic or cannot be read.
 *
 * isAuthenticationFailure() distinguishes forged or unauthorised requests
 * (answered with HTTP 403) from malformed ones (HTTP 400).
 */
final class InvalidNotification extends ComfinoException
{
    private bool $authentication = true;

    public static function missingSignature(): self
    {
        return new self('ComfinoPay notification has no CR-Signature header.');
    }

    public static function invalidSignature(): self
    {
        return new self('ComfinoPay notification signature does not match.');
    }

    public static function forbiddenSender(string $ip): self
    {
        return new self(sprintf('ComfinoPay notification sent from a disallowed address %s.', $ip));
    }

    public static function malformed(string $reason = 'the body is not a JSON object'): self
    {
        $exception = new self('ComfinoPay notification is malformed: ' . $reason . '.');
        $exception->authentication = false;

        return $exception;
    }

    public function isAuthenticationFailure(): bool
    {
        return $this->authentication;
    }
}
