<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Exceptions\InvalidOrder;

/**
 * Cart of a loan application; all amounts are gross minor units (grosz).
 *
 * ComfinoPay requires totalAmount to equal the sum of price x quantity of
 * all positions plus deliveryCost. make() computes the total; the
 * constructor validates a total given explicitly.
 */
final readonly class Cart
{
    /**
     * @param list<CartItem> $items
     *
     * @throws InvalidOrder When the cart is empty or the total does not match the positions.
     */
    public function __construct(
        public array $items,
        public int $totalAmount,
        public ?int $deliveryCost = null,
        public ?int $deliveryNetCost = null,
        public ?int $deliveryCostVatRate = null,
        public ?int $deliveryCostVatAmount = null,
        public ?string $category = null,
    ) {
        if ($items === []) {
            throw InvalidOrder::because('the cart must contain at least one product');
        }

        $expected = $this->itemsTotal() + ($deliveryCost ?? 0);

        if ($expected !== $totalAmount) {
            throw InvalidOrder::because(sprintf(
                'cart totalAmount %d differs from the sum of positions and delivery %d; add an ADDITIONAL_FEE or DISCOUNT position',
                $totalAmount,
                $expected,
            ));
        }
    }

    /**
     * Builds a cart whose total is the sum of the positions and the delivery cost.
     *
     * @param list<CartItem> $items
     */
    public static function make(array $items, ?int $deliveryCost = null, ?string $category = null): self
    {
        $total = array_sum(array_map(static fn(CartItem $item): int => $item->total(), $items)) + ($deliveryCost ?? 0);

        return new self($items, $total, $deliveryCost, category: $category);
    }

    public function itemsTotal(): int
    {
        return array_sum(array_map(static fn(CartItem $item): int => $item->total(), $this->items));
    }

    /**
     * The cart section of the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'products' => array_map(static fn(CartItem $item): array => $item->toArray(), $this->items),
            'totalAmount' => $this->totalAmount,
            'deliveryCost' => $this->deliveryCost,
            'deliveryNetCost' => $this->deliveryNetCost,
            'deliveryCostVatRate' => $this->deliveryCostVatRate,
            'deliveryCostVatAmount' => $this->deliveryCostVatAmount,
            'category' => $this->category,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
