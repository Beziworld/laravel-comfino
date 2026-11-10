<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('prints the account status, widget key, product types and offers', function (): void {
    Http::fake([
        '*/v1/user/is-active' => Http::response('true'),
        '*/v1/product-types*' => Http::response(['PAY_LATER' => ['Pay later', 'Kup teraz, zapłać później']]),
        '*/v1/financial-products*' => Http::response(comfinoFixture('financial-products')),
    ]);

    $this->artisan('comfino:status', ['--amount' => '1200'])
        ->expectsOutputToContain('Account active: yes')
        ->expectsOutputToContain('Widget key: test-widget-key-1a2b3c')
        ->expectsOutputToContain('Kup teraz, zapłać później')
        ->expectsOutputToContain('23.84')
        ->assertSuccessful();

    Http::assertSent(fn($request): bool => str_contains($request->url(), 'loanAmount=120000'));
});

it('fails with the API error message', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Authorization failed'], 401)]);

    $this->artisan('comfino:status', ['--connection' => 'second'])
        ->expectsOutputToContain('Authorization failed')
        ->assertFailed();
});
