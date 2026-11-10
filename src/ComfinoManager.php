<?php

declare(strict_types=1);

namespace Beziworld\Comfino;

use Beziworld\Comfino\Exceptions\InvalidConfiguration;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Resolves ComfinoPay clients for any number of shop accounts.
 *
 * Named connections are defined in config/comfino.php in the same way as
 * database connections; options outside "connections" are shared defaults.
 * Clients for credentials stored at runtime (for example per tenant) are
 * created with using(). Runtime connections that receive notifications are
 * registered with resolveConnectionsUsing(), so the notification route can
 * find their API key by name. Calls made directly on the manager go to the
 * default connection.
 *
 * @mixin ComfinoClient
 */
final class ComfinoManager
{
    /** @var array<string, ComfinoClient> */
    private array $clients = [];

    /** @var (Closure(string): (ComfinoConfig|array<string, mixed>|null))|null */
    private ?Closure $resolver = null;

    /**
     * @param (Closure(ComfinoConfig): ?string)|null $notifyUrlResolver
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
        private readonly ?Closure $notifyUrlResolver = null,
    ) {}

    /**
     * Client of a configured (or resolver-provided) connection; the default connection when no name is given.
     *
     * @throws InvalidConfiguration When the connection is not defined or incomplete.
     */
    public function connection(?string $name = null): ComfinoClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= $this->make($this->configFor($name));
    }

    /**
     * Client for credentials provided at runtime, merged with the shared options.
     *
     * Give the array a "name" to receive notifications on the package route
     * under that name; the name must then be resolvable through
     * resolveConnectionsUsing().
     *
     * @param ComfinoConfig|array<string, mixed> $config Keys as in a connection: api_key, widget_key, sandbox, ...
     */
    public function using(ComfinoConfig|array $config): ComfinoClient
    {
        return $this->make($config instanceof ComfinoConfig ? $config : ComfinoConfig::fromArray(self::merge($this->shared(), $config)));
    }

    /**
     * Registers a resolver of connections that are not defined in the configuration file.
     *
     * The resolver receives the connection name (e.g. a tenant key taken from
     * the notification URL) and returns its configuration or null when unknown.
     *
     * @param Closure(string): (ComfinoConfig|array<string, mixed>|null) $resolver
     */
    public function resolveConnectionsUsing(Closure $resolver): void
    {
        $this->resolver = $resolver;
        $this->clients = [];
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config->get('comfino.default');

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
     * Names of the configured connections.
     *
     * @return list<string>
     */
    public function connectionNames(): array
    {
        $connections = $this->config->get('comfino.connections');

        if (! is_array($connections) || $connections === []) {
            return [$this->getDefaultConnection()];
        }

        return array_map(strval(...), array_keys($connections));
    }

    /**
     * Forgets resolved clients, for example after the configuration changed.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->connection(), $method];

        if (! is_callable($callable)) {
            throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', ComfinoClient::class, $method));
        }

        return $callable(...$arguments);
    }

    private function make(ComfinoConfig $config): ComfinoClient
    {
        return new ComfinoClient($this->http, $config, $this->notifyUrlResolver);
    }

    private function configFor(string $name): ComfinoConfig
    {
        $connections = $this->config->get('comfino.connections');
        $connection = is_array($connections) ? ($connections[$name] ?? null) : null;

        if (is_array($connection)) {
            return ComfinoConfig::fromArray(self::merge($this->shared(), $connection), $name);
        }

        if ((! is_array($connections) || $connections === []) && $name === $this->getDefaultConnection()) {
            // Single-account configuration with credentials at the top level.
            return ComfinoConfig::fromArray($this->shared(), $name);
        }

        $resolved = $this->resolver !== null ? ($this->resolver)($name) : null;

        if ($resolved instanceof ComfinoConfig) {
            return $resolved;
        }

        if (is_array($resolved)) {
            return ComfinoConfig::fromArray(self::merge($this->shared(), $resolved + ['name' => $name]), $name);
        }

        throw InvalidConfiguration::unknownConnection($name);
    }

    /**
     * Applies connection options over the shared ones; empty values (unset environment variables) do not override.
     *
     * @param array<string, mixed> $shared
     * @param array<array-key, mixed> $connection
     * @return array<string, mixed>
     */
    private static function merge(array $shared, array $connection): array
    {
        foreach ($connection as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $key = (string) $key;

            if ($key === 'widget' && is_array($value) && is_array($shared['widget'] ?? null)) {
                $value = array_replace($shared['widget'], $value);
            }

            $shared[$key] = $value;
        }

        return $shared;
    }

    /**
     * Options shared by every connection: everything outside "default", "connections" and "notifications".
     *
     * @return array<string, mixed>
     */
    private function shared(): array
    {
        $config = $this->config->get('comfino', []);
        $config = is_array($config) ? $config : [];
        unset($config['default'], $config['connections'], $config['notifications']);

        /** @var array<string, mixed> $config */
        return $config;
    }
}
