<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Support;

use InvalidArgumentException;

/**
 * Conversion between decimal amounts and the integer minor units (grosz) used by the ComfinoPay API.
 */
final class Amount
{
    /**
     * Converts a decimal amount such as "1499.99", 1499.99 or "1 499,99" to minor units (149999).
     *
     * Integers are treated as whole currency units; pass minor units directly to the data objects instead.
     *
     * @throws InvalidArgumentException When the value is not a valid amount.
     */
    public static function toMinor(int|float|string $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($amount));

        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid amount.', $amount));
        }

        $minor = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '0', 2, '0');

        return $matches[1] === '-' ? -$minor : $minor;
    }

    /**
     * Formats minor units as a decimal string with two fraction digits, e.g. 149999 => "1499.99".
     */
    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($minor, 100), $minor % 100);
    }
}
