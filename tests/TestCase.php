<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Tests;

use Beziworld\Comfino\ComfinoServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string API_KEY = 'test-api-key-0f9e8d7c';

    public const string WIDGET_KEY = 'test-widget-key-1a2b3c';

    public const string SANDBOX = 'https://api-ecommerce.craty.pl';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [ComfinoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://shop.example.com');
        $app['url']->forceRootUrl('https://shop.example.com');
        $app['url']->forceScheme('https');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('comfino.connections.default', [
            'api_key' => self::API_KEY,
            'widget_key' => self::WIDGET_KEY,
        ]);
        $app['config']->set('comfino.connections.second', [
            'api_key' => 'second-api-key',
        ]);
        $app['config']->set('comfino.sandbox', true);
        $app['config']->set('comfino.retry_delay', 0);
        $app['config']->set('comfino.return_url', 'https://shop.example.com/comfino/return');
    }
}
