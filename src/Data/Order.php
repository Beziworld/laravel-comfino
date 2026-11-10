<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Loan application read from GET /v1/orders/{orderId}.
 *
 * The response layout follows the parser of the official client; fields
 * that are missing in the response are null and raw keeps the full payload.
 */
final readonly class Order
{
    /**
     * @param list<string> $allowedProductTypes
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $orderId,
        public string $statusValue,
        public ?OrderStatus $status,
        public ?CarbonImmutable $createdAt = null,
        public ?string $applicationUrl = null,
        public ?string $notifyUrl = null,
        public ?string $returnUrl = null,
        public ?Money $loanAmount = null,
        public ?Money $maxLoanAmount = null,
        public ?int $loanTerm = null,
        public ?string $loanTypeValue = null,
        public ?LoanType $loanType = null,
        public array $allowedProductTypes = [],
        public ?Money $totalAmount = null,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $currency = 'PLN', string $timezone = 'Europe/Warsaw', string $fallbackId = ''): self
    {
        $status = Values::string($data['status'] ?? null) ?? '';
        $loan = Values::array($data['loanParameters'] ?? null);
        $cart = Values::array($data['cart'] ?? null);
        $type = Values::string($loan['type'] ?? null);
        $money = static fn(mixed $value): ?Money => ($amount = Values::int($value)) !== null ? new Money($amount, $currency) : null;

        return new self(
            orderId: Values::string($data['orderId'] ?? $data['externalId'] ?? null) ?? $fallbackId,
            statusValue: $status,
            status: OrderStatus::tryFrom($status),
            createdAt: Values::date($data['createdAt'] ?? null, $timezone),
            applicationUrl: Values::string($data['applicationUrl'] ?? null),
            notifyUrl: Values::string($data['notifyUrl'] ?? null),
            returnUrl: Values::string($data['returnUrl'] ?? null),
            loanAmount: $money($loan['amount'] ?? null),
            maxLoanAmount: $money($loan['maxAmount'] ?? null),
            loanTerm: Values::int($loan['term'] ?? null),
            loanTypeValue: $type,
            loanType: $type !== null ? LoanType::tryFrom($type) : null,
            allowedProductTypes: array_values(array_filter(Values::array($loan['allowedProductTypes'] ?? null), is_string(...))),
            totalAmount: $money($cart['totalAmount'] ?? null),
            raw: $data,
        );
    }
}
