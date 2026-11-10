<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

/**
 * Customer address of a loan application; every field is optional.
 */
final readonly class Address
{
    public function __construct(
        public ?string $street = null,
        public ?string $buildingNumber = null,
        public ?string $apartmentNumber = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = 'PL',
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'street' => $this->street,
            'buildingNumber' => $this->buildingNumber,
            'apartmentNumber' => $this->apartmentNumber,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'countryCode' => $this->countryCode !== null ? strtoupper($this->countryCode) : null,
        ], static fn(?string $value): bool => $value !== null && $value !== '');
    }
}
