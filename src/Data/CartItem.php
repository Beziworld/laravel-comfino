<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Exceptions\InvalidOrder;

/**
 * Cart position of a loan application; prices are gross amounts in minor units (grosz).
 *
 * Additional costs (payment surcharge, handling) are positions of category
 * ADDITIONAL_FEE, discounts positions of category DISCOUNT with a negative
 * price. A null vatRate means VAT exempt.
 */
final readonly class CartItem
{
    public const string CATEGORY_ADDITIONAL_FEE = 'ADDITIONAL_FEE';

    public const string CATEGORY_DISCOUNT = 'DISCOUNT';

    /**
     * @throws InvalidOrder When the name is empty or the quantity is not positive.
     */
    public function __construct(
        public string $name,
        public int $price,
        public int $quantity = 1,
        public ?int $netPrice = null,
        public ?int $vatRate = null,
        public ?int $vatAmount = null,
        public ?string $photoUrl = null,
        public ?string $ean = null,
        public ?string $externalId = null,
        public ?string $category = null,
    ) {
        if (trim($name) === '') {
            throw InvalidOrder::because('cart item name must not be empty');
        }

        if ($quantity <= 0) {
            throw InvalidOrder::because(sprintf('quantity of "%s" must be positive', $name));
        }
    }

    /**
     * Additional cost increasing the loan amount, e.g. a payment surcharge.
     */
    public static function fee(string $name, int $price): self
    {
        return new self($name, $price, category: self::CATEGORY_ADDITIONAL_FEE);
    }

    /**
     * Discount decreasing the loan amount; the amount is stored as a negative price.
     */
    public static function discount(string $name, int $amount): self
    {
        return new self($name, -abs($amount), category: self::CATEGORY_DISCOUNT);
    }

    public function total(): int
    {
        return $this->price * $this->quantity;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'photoUrl' => $this->photoUrl,
            'ean' => $this->ean,
            'externalId' => $this->externalId,
            'category' => $this->category,
            'netPrice' => $this->netPrice,
            'vatRate' => $this->vatRate,
            'vatAmount' => $this->vatAmount,
        ], static fn(int|string|null $value): bool => $value !== null);
    }
}
