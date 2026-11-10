<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Exceptions\InvalidOrder;

/**
 * Customer data of a loan application.
 *
 * ComfinoPay expects first and last names of 2 to 63 characters, a nine
 * digit phone number and the IP address of the customer. The phone number
 * is normalised: separators and the +48 / 0048 prefix are removed.
 */
final readonly class Customer
{
    public string $phoneNumber;

    /**
     * @throws InvalidOrder When a documented constraint is violated.
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        string $phoneNumber,
        public string $ip,
        public ?string $taxId = null,
        public ?bool $regular = null,
        public ?bool $logged = null,
        public ?Address $address = null,
    ) {
        foreach (['firstName' => $firstName, 'lastName' => $lastName] as $field => $value) {
            $length = mb_strlen(trim($value));

            if ($length < 2 || $length > 63) {
                throw InvalidOrder::because(sprintf('customer %s must have 2 to 63 characters', $field));
            }
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidOrder::because('customer email is not a valid e-mail address');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw InvalidOrder::because('customer ip must be an IPv4 or IPv6 address');
        }

        $this->phoneNumber = self::normalizePhone($phoneNumber);
    }

    /**
     * Splits a full name stored in one field: the first word becomes the first name, the rest the last name.
     */
    public static function fromFullName(
        string $fullName,
        string $email,
        string $phoneNumber,
        string $ip,
        ?string $taxId = null,
        ?bool $regular = null,
        ?bool $logged = null,
        ?Address $address = null,
    ): self {
        $parts = preg_split('/\s+/u', trim($fullName), 2);
        $parts = is_array($parts) ? $parts : [''];

        return new self($parts[0], $parts[1] ?? '', $email, $phoneNumber, $ip, $taxId, $regular, $logged, $address);
    }

    /**
     * @throws InvalidOrder When the number does not have nine digits after normalisation.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '0048')) {
            $digits = substr($digits, 4);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 9) {
            throw InvalidOrder::because('customer phoneNumber must have 9 digits');
        }

        return $digits;
    }

    /**
     * The customer section of the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $address = $this->address?->toArray();

        return array_filter([
            'firstName' => trim($this->firstName),
            'lastName' => trim($this->lastName),
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
            'taxId' => $this->taxId !== null ? preg_replace('/\D+/', '', $this->taxId) : null,
            'ip' => $this->ip,
            'regular' => $this->regular,
            'logged' => $this->logged,
            'address' => $address !== null && $address !== [] ? $address : null,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }
}
