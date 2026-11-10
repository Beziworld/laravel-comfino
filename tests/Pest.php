<?php

declare(strict_types=1);

use Beziworld\Comfino\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Decoded JSON fixture from tests/Fixtures.
 *
 * @return array<array-key, mixed>
 */
function comfinoFixture(string $name): array
{
    return json_decode(comfinoRawFixture($name), true, 64, JSON_THROW_ON_ERROR);
}

/**
 * Raw JSON fixture body.
 */
function comfinoRawFixture(string $name): string
{
    return trim((string) file_get_contents(__DIR__ . '/Fixtures/' . $name . '.json'));
}
