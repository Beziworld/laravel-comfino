<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Exceptions;

use Throwable;

/**
 * Raised when ComfinoPay rejects a request, answers unexpectedly or cannot be reached.
 *
 * httpStatus() follows the documented codes: 400 validation (errors() lists
 * field messages), 401 "Authorization failed" (wrong key or a key of the
 * other environment), 403, 404, 405, 409 conflict, 415, 422, 429 and 5xx.
 */
final class ApiException extends ComfinoException
{
    /**
     * @param array<string, list<string>> $errors Field name (or "_" for general messages) mapped to messages.
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly array $errors = [],
        private readonly array $payload = [],
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromResponse(array $payload, int $httpStatus, ?int $retryAfter = null): self
    {
        $errors = self::parseErrors($payload);
        $parts = [];

        foreach ($errors as $field => $messages) {
            $parts[] = ($field === '_' ? '' : $field . ': ') . implode(', ', $messages);
        }

        $message = $parts !== [] ? implode('; ', $parts) : sprintf('request failed with HTTP status %d', $httpStatus);

        if ($httpStatus === 429) {
            $message = 'rate limit exceeded' . ($retryAfter !== null ? sprintf(', retry after %d seconds', $retryAfter) : '');
        }

        return new self(sprintf('ComfinoPay error (HTTP %d): %s', $httpStatus, $message), $httpStatus, $errors, $payload, $retryAfter);
    }

    public static function transport(Throwable $previous): self
    {
        return new self('Could not connect to ComfinoPay: ' . $previous->getMessage(), previous: $previous);
    }

    public static function unexpectedResponse(string $what, ?int $httpStatus = null): self
    {
        return new self(sprintf('ComfinoPay returned an unexpected response: %s.', $what), $httpStatus);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Validation messages keyed by field; general messages are stored under "_".
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Seconds from the Retry-After header of an HTTP 429 response.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isValidationError(): bool
    {
        return $this->httpStatus === 400 || $this->httpStatus === 422;
    }

    public function isUnauthorized(): bool
    {
        return $this->httpStatus === 401;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }

    public function isConflict(): bool
    {
        return $this->httpStatus === 409;
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }

    public function isServerError(): bool
    {
        return $this->httpStatus !== null && $this->httpStatus >= 500;
    }

    /**
     * Reads "errors" (field => message or list of messages), "message", or a plain field map.
     *
     * @param array<array-key, mixed> $payload
     * @return array<string, list<string>>
     */
    private static function parseErrors(array $payload): array
    {
        $source = $payload['errors'] ?? null;

        if (! is_array($source)) {
            if (array_key_exists('message', $payload)) {
                $source = ['_' => $payload['message']];
            } elseif ($payload !== [] && ! array_is_list($payload)) {
                $source = $payload;
            } else {
                return [];
            }
        }

        $errors = [];

        foreach ($source as $field => $messages) {
            $field = is_int($field) ? '_' : $field;
            $list = self::messages($messages);

            if ($list !== []) {
                $errors[$field] = array_merge($errors[$field] ?? [], $list);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function messages(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (is_int($value) || is_float($value)) {
            return [(string) $value];
        }

        if (is_bool($value)) {
            return [$value ? 'true' : 'false'];
        }

        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            array_push($list, ...self::messages($item));
        }

        return $list;
    }
}
