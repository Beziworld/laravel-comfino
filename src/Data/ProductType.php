<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Data;

use Beziworld\Comfino\Enums\LoanType;

/**
 * Financial product type enabled for the shop account, with its internal and public names.
 */
final readonly class ProductType
{
    public function __construct(
        public string $code,
        public ?LoanType $type,
        public string $name,
        public string $publicName,
    ) {}

    /**
     * Builds the list from the "type => [internalName, publicName]" map returned by /v1/product-types.
     *
     * @param array<array-key, mixed> $payload
     * @return list<self>
     */
    public static function listFromArray(array $payload): array
    {
        $types = [];

        foreach ($payload as $code => $names) {
            $code = (string) $code;
            $names = is_array($names) ? array_values($names) : [$names];
            $name = is_string($names[0] ?? null) ? $names[0] : $code;
            $public = is_string($names[1] ?? null) ? $names[1] : $name;

            $types[] = new self($code, LoanType::tryFrom($code), $name, $public);
        }

        return $types;
    }
}
