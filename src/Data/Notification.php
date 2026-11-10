<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Enums\PaymentMethod;
use Beziworld\Comfino\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Status notification sent by ComfinoPay to notifyUrl.
 *
 * externalId is the orderId given by the shop. Enum properties are null
 * for values outside the known dictionaries; the *Value properties keep
 * the raw strings.
 */
final readonly class Notification
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $externalId,
        public string $statusValue,
        public ?OrderStatus $status,
        public ?CarbonImmutable $changedAt = null,
        public ?string $paymentMethodValue = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $productTypeValue = null,
        public ?LoanType $productType = null,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $timezone = 'Europe/Warsaw'): self
    {
        $status = Values::string($data['status'] ?? null) ?? '';
        $method = Values::string($data['paymentMethod'] ?? null);
        $product = Values::string($data['productType'] ?? null);

        return new self(
            externalId: Values::string($data['externalId'] ?? null) ?? '',
            statusValue: $status,
            status: OrderStatus::tryFrom($status),
            changedAt: Values::date($data['changedAt'] ?? null, $timezone),
            paymentMethodValue: $method,
            paymentMethod: $method !== null ? PaymentMethod::tryFrom($method) : null,
            productTypeValue: $product,
            productType: $product !== null ? LoanType::tryFrom($product) : null,
            raw: $data,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status?->isSuccessful() ?? false;
    }

    public function isFailed(): bool
    {
        return $this->status?->isFailed() ?? false;
    }
}
