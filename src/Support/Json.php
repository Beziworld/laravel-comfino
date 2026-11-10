<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Support;

/**
 * JSON encoding with the flags of the official ComfinoPay client, so that signed sections hash identically.
 *
 * @internal
 */
final class Json
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
