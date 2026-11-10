<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Tolerant readers of scalar values from decoded ComfinoPay payloads.
 *
 * @internal
 */
final class Values
{
    public static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) round((float) $value);
        }

        return null;
    }

    public static function float(mixed $value): ?float
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Reads a Unix timestamp (seconds, or milliseconds when larger than 10^11) or a date string.
     */
    public static function date(mixed $value, string $timezone): ?CarbonImmutable
    {
        try {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $timestamp = (int) $value;

                if ($timestamp > 100_000_000_000) {
                    return CarbonImmutable::createFromTimestampMs($timestamp, $timezone);
                }

                return CarbonImmutable::createFromTimestamp($timestamp, $timezone);
            }

            if (is_string($value) && trim($value) !== '') {
                return CarbonImmutable::parse(trim($value), $timezone)->setTimezone($timezone);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
