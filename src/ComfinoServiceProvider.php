<?php

declare(strict_types=1);

namespace Beziworld\Comfino;

use Beziworld\Comfino\Console\StatusCommand;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Registers the ComfinoPay connection manager, the client of the default connection and the notification route.
 */
final class ComfinoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/comfino.php', 'comfino');

        $this->app->singleton(ComfinoManager::class, static function (Application $app): ComfinoManager {
            $config = $app->make(Config::class);

            return new ComfinoManager(
                $app->make(HttpFactory::class),
                $config,
                static function (ComfinoConfig $comfino) use ($app, $config): ?string {
                    $route = (string) $config->get('comfino.notifications.name', 'comfino.notification');

                    if ($comfino->name === null || ! (bool) $config->get('comfino.notifications.enabled', true)) {
                        return null;
                    }

                    try {
                        return $app->make(UrlGenerator::class)->route($route, ['connection' => $comfino->name]);
                    } catch (RouteNotFoundException) {
                        return null;
                    }
                },
            );
        });

        // The client and configuration of the default connection.
        $this->app->bind(ComfinoClient::class, static fn(Application $app): ComfinoClient => $app->make(ComfinoManager::class)->connection());
        $this->app->bind(ComfinoConfig::class, static fn(Application $app): ComfinoConfig => $app->make(ComfinoClient::class)->config());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/comfino.php' => $this->app->configPath('comfino.php')], 'comfino-config');
            $this->commands([StatusCommand::class]);
        }

        if ((bool) $this->app->make(Config::class)->get('comfino.notifications.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/comfino.php');
        }
    }
}
