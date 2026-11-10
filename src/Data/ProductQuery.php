<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Exceptions\InvalidOrder;

/**
 * Criteria of a financial products query.
 *
 * loanAmount is in minor units (grosz). A null or zero loanTerm returns
 * offers for every available term; productTypes limits the result to the
 * given types (an empty list returns no offers at all).
 */
final readonly class ProductQuery
{
    /**
     * @param list<LoanType|string>|null $productTypes
     *
     * @throws InvalidOrder When the amount is not positive.
     */
    public function __construct(
        public int $loanAmount,
        public ?int $loanTerm = null,
        public LoanType|string|null $loanType = null,
        public LoanType|string|null $loanTypeSelected = null,
        public ?array $productTypes = null,
        public ?string $taxId = null,
    ) {
        if ($loanAmount <= 0) {
            throw InvalidOrder::because('loanAmount must be a positive number of grosz');
        }
    }

    /**
     * Query string parameters of /v1/financial-products.
     *
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        $query = ['loanAmount' => $this->loanAmount];

        if ($this->loanTerm !== null) {
            $query['loanTerm'] = $this->loanTerm;
        }

        if ($this->loanType !== null) {
            $query['loanType'] = LoanType::values([$this->loanType])[0];
        }

        if ($this->loanTypeSelected !== null) {
            $query['loanTypeSelected'] = LoanType::values([$this->loanTypeSelected])[0];
        }

        if ($this->productTypes !== null) {
            $query['productTypes'] = implode(',', LoanType::values($this->productTypes));
        }

        if ($this->taxId !== null && $this->taxId !== '') {
            $query['taxId'] = $this->taxId;
        }

        return $query;
    }
}
