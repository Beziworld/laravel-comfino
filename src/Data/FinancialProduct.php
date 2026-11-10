<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Support\Values;

/**
 * Financial product offered for a loan amount, with its default variant and all instalment variants.
 *
 * type is null for product types outside the known dictionary; typeValue keeps the raw value.
 */
final readonly class FinancialProduct
{
    /**
     * @param list<LoanOption> $options
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $name,
        public string $typeValue,
        public ?LoanType $type,
        public LoanOption $defaultOption,
        public array $options,
        public ?string $description = null,
        public ?string $creditorName = null,
        public ?string $icon = null,
        public ?string $representativeExample = null,
        public ?string $remarks = null,
        public ?float $offerRate = null,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $currency = 'PLN'): self
    {
        $typeValue = Values::string($data['type'] ?? null) ?? '';
        $options = [];

        foreach (Values::array($data['loanParameters'] ?? null) as $option) {
            if (is_array($option)) {
                $options[] = LoanOption::fromArray($option, $currency);
            }
        }

        return new self(
            name: Values::string($data['name'] ?? null) ?? $typeValue,
            typeValue: $typeValue,
            type: LoanType::tryFrom($typeValue),
            defaultOption: LoanOption::fromArray($data, $currency),
            options: $options,
            description: Values::string($data['description'] ?? null),
            creditorName: Values::string($data['creditorName'] ?? null),
            icon: Values::string($data['icon'] ?? null),
            representativeExample: Values::string($data['representativeExample'] ?? null),
            remarks: Values::string($data['remarks'] ?? null),
            offerRate: Values::float($data['offerRate'] ?? null),
            raw: $data,
        );
    }

    /**
     * Variant with the given number of instalments.
     */
    public function option(int $term): ?LoanOption
    {
        foreach ($this->options as $option) {
            if ($option->term === $term) {
                return $option;
            }
        }

        return $this->defaultOption->term === $term ? $this->defaultOption : null;
    }

    /**
     * Variant with the lowest instalment amount.
     */
    public function lowestInstalment(): LoanOption
    {
        $lowest = $this->defaultOption;

        foreach ($this->options as $option) {
            if ($option->instalment->amount > 0 && ($lowest->instalment->amount <= 0 || $option->instalment->amount < $lowest->instalment->amount)) {
                $lowest = $option;
            }
        }

        return $lowest;
    }

    /**
     * Available numbers of instalments.
     *
     * @return list<int>
     */
    public function terms(): array
    {
        $terms = array_map(static fn(LoanOption $option): int => $option->term, $this->options);

        if ($terms === []) {
            $terms = [$this->defaultOption->term];
        }

        sort($terms);

        return array_values(array_unique($terms));
    }
}
