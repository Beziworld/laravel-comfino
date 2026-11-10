<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Support\Values;

/**
 * Loan application registered by ComfinoPay; redirect the customer to applicationUrl.
 */
final readonly class CreatedOrder
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $externalId,
        public string $applicationUrl,
        public string $statusValue,
        public ?OrderStatus $status,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = Values::string($data['status'] ?? null) ?? OrderStatus::Created->value;

        return new self(
            externalId: Values::string($data['externalId'] ?? null) ?? '',
            applicationUrl: Values::string($data['applicationUrl'] ?? null) ?? '',
            statusValue: $status,
            status: OrderStatus::tryFrom($status),
            raw: $data,
        );
    }
}
