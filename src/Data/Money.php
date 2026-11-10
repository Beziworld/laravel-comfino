<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Support\Amount;

/**
 * Amount in minor units (grosz) with its currency.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency = 'PLN',
    ) {}

    /**
     * Decimal representation with two fraction digits, e.g. "1499.99".
     */
    public function decimal(): string
    {
        return Amount::toDecimal($this->amount);
    }

    public function __toString(): string
    {
        return $this->decimal() . ' ' . $this->currency;
    }
}
