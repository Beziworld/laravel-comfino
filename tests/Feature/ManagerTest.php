<?php

declare(strict_types=1);

use Beziworld\Comfino\ComfinoClient;
use Beziworld\Comfino\ComfinoConfig;
use Beziworld\Comfino\ComfinoManager;
use Beziworld\Comfino\Exceptions\InvalidConfiguration;
use Beziworld\Comfino\Facades\Comfino;
use Beziworld\Comfino\Tests\TestCase;

it('resolves the default connection from the container', function (): void {
    $client = app(ComfinoClient::class);

    expect($client->connectionName())->toBe('default')
        ->and(app(ComfinoConfig::class)->apiKey)->toBe(TestCase::API_KEY)
        ->and(Comfino::connection())->toBe(Comfino::connection('default'))
        ->and(Comfino::connectionNames())->toBe(['default', 'second']);
});

it('builds the notification URL of each connection', function (): void {
    expect(Comfino::connection()->notifyUrl())->toBe('https://shop.example.com/comfino/notification/default')
        ->and(Comfino::connection('second')->notifyUrl())->toBe('https://shop.example.com/comfino/notification/second')
        ->and(Comfino::using(['api_key' => 'x'])->notifyUrl())->toBeNull()
        ->and(Comfino::using(['api_key' => 'x', 'notify_url' => 'https://hooks.example.com/c'])->notifyUrl())->toBe('https://hooks.example.com/c');
});

it('merges shared options into runtime connections', function (): void {
    config()->set('comfino.widget.type', 'classic');
    app()->forgetInstance(ComfinoManager::class);

    $config = Comfino::using(['api_key' => 'tenant-key', 'sandbox' => '0', 'widget' => ['embed_method' => 'INSERT_AFTER']])->config();

    expect($config->apiKey)->toBe('tenant-key')
        ->and($config->sandbox)->toBeFalse()
        ->and($config->apiUrl())->toBe(ComfinoConfig::PRODUCTION_URL)
        ->and($config->returnUrl)->toBe('https://shop.example.com/comfino/return')
        ->and($config->widget['type'])->toBe('classic')
        ->and($config->widget['embed_method'])->toBe('INSERT_AFTER');
});

it('fails clearly for unknown or incomplete connections', function (): void {
    expect(fn() => Comfino::connection('missing'))->toThrow(InvalidConfiguration::class, 'connection "missing" is not defined')
        ->and(fn() => Comfino::using(['api_key' => '  ']))->toThrow(InvalidConfiguration::class, 'COMFINO_API_KEY');
});

it('supports a single account configured at the top level', function (): void {
    config()->set('comfino.connections', []);
    config()->set('comfino.api_key', 'top-level-key');
    app()->forgetInstance(ComfinoManager::class);

    expect(Comfino::connection()->config()->apiKey)->toBe('top-level-key');
});

it('honours a custom base URL and normalises options', function (): void {
    $config = ComfinoConfig::fromArray([
        'api_key' => 'k',
        'base_url' => 'https://staging.comfino.pl/',
        'language' => 'EN',
        'currency' => 'eur',
        'timeout' => 'abc',
        'retries' => '0',
    ]);

    expect($config->apiUrl())->toBe('https://staging.comfino.pl')
        ->and($config->language)->toBe('en')
        ->and($config->currency)->toBe('EUR')
        ->and($config->timeout)->toBe(15)
        ->and($config->retries)->toBe(0)
        ->and($config->connectTimeout)->toBe(5);
});

it('forwards unknown methods to the default client and rejects missing ones', function (): void {
    expect(Comfino::config()->name)->toBe('default')
        ->and(fn() => app(ComfinoManager::class)->doesNotExist())->toThrow(BadMethodCallException::class);
});

it('publishes the configuration file', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'comfino-config', '--force' => true])->assertSuccessful();

    expect(file_exists(config_path('comfino.php')))->toBeTrue();

    @unlink(config_path('comfino.php'));
});
