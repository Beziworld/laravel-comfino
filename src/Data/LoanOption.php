<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Support\Values;

/**
 * One instalment variant of a financial product, or the best possible offer for an amount.
 *
 * rrso is the annual percentage rate as a fraction (0.2384 means 23.84%).
 */
final readonly class LoanOption
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $term,
        public Money $instalment,
        public Money $toPay,
        public ?float $rrso = null,
        public ?Money $initialPayment = null,
        public ?float $initialPaymentRate = null,
        public ?Money $redemptionPayment = null,
        public ?float $redemptionPaymentRate = null,
        public ?float $interest = null,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $currency = 'PLN'): self
    {
        $initial = Values::int($data['initialPaymentValue'] ?? null);
        $redemption = Values::int($data['redemptionPaymentValue'] ?? null);

        return new self(
            term: Values::int($data['loanTerm'] ?? null) ?? 0,
            instalment: new Money(Values::int($data['instalmentAmount'] ?? null) ?? 0, $currency),
            toPay: new Money(Values::int($data['toPay'] ?? null) ?? 0, $currency),
            rrso: Values::float($data['rrso'] ?? null),
            initialPayment: $initial !== null ? new Money($initial, $currency) : null,
            initialPaymentRate: Values::float($data['initialPaymentRate'] ?? null),
            redemptionPayment: $redemption !== null ? new Money($redemption, $currency) : null,
            redemptionPaymentRate: Values::float($data['redemptionPaymentRate'] ?? null),
            interest: Values::float($data['interest'] ?? null),
            raw: $data,
        );
    }

    /**
     * Annual percentage rate formatted as a percentage with two decimals, e.g. "23.84".
     */
    public function rrsoPercent(): ?string
    {
        return $this->rrso !== null ? number_format($this->rrso * 100, 2, '.', '') : null;
    }
}
