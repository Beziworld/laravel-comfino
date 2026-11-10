<?php

declare(strict_types=1);

namespace Beziworld\Comfino;

use Beziworld\Comfino\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable configuration of one ComfinoPay shop account.
 */
final readonly class ComfinoConfig
{
    public const string PRODUCTION_URL = 'https://api-ecommerce.comfino.pl';

    public const string SANDBOX_URL = 'https://api-ecommerce.craty.pl';

    public const string PRODUCTION_WIDGET_SCRIPT = 'https://widget.comfino.pl/v2/widget-frontend.min.js';

    public const string SANDBOX_WIDGET_SCRIPT = 'https://widget.craty.pl/v2/widget-frontend.min.js';

    /**
     * @param array<string, mixed> $widget Widget defaults (script_url, type, embed_method, price_selector, target_selector, offer_types).
     * @param string|null $name Connection name; it is embedded in the default notification URL.
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $apiKey,
        public ?string $widgetKey = null,
        public bool $sandbox = true,
        public ?string $baseUrl = null,
        public ?string $returnUrl = null,
        public ?string $notifyUrl = null,
        public string $language = 'pl',
        public string $currency = 'PLN',
        public int $timeout = 15,
        public int $connectTimeout = 5,
        public int $retries = 1,
        public int $retryDelay = 300,
        public string $timezone = 'Europe/Warsaw',
        public array $widget = [],
        public ?string $name = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfiguration When the API key is missing.
     */
    public static function fromArray(array $config, ?string $name = null): self
    {
        $language = self::string($config['language'] ?? null);
        $currency = self::string($config['currency'] ?? null);
        $widget = $config['widget'] ?? [];

        /** @var array<string, mixed> $widget */
        $widget = is_array($widget) ? $widget : [];

        return new self(
            apiKey: self::string($config['api_key'] ?? null) ?? throw InvalidConfiguration::missing('api_key', 'COMFINO_API_KEY'),
            widgetKey: self::string($config['widget_key'] ?? null),
            sandbox: filter_var($config['sandbox'] ?? true, FILTER_VALIDATE_BOOL),
            baseUrl: self::string($config['base_url'] ?? null),
            returnUrl: self::string($config['return_url'] ?? null),
            notifyUrl: self::string($config['notify_url'] ?? null),
            language: $language !== null ? strtolower($language) : 'pl',
            currency: $currency !== null ? strtoupper($currency) : 'PLN',
            timeout: self::positive($config['timeout'] ?? null, 15),
            connectTimeout: self::positive($config['connect_timeout'] ?? null, 5),
            retries: self::natural($config['retries'] ?? null, 1),
            retryDelay: self::natural($config['retry_delay'] ?? null, 300),
            timezone: self::string($config['timezone'] ?? null) ?? 'Europe/Warsaw',
            widget: $widget,
            name: self::string($config['name'] ?? null) ?? $name,
        );
    }

    /**
     * Base URL of the API without a trailing slash and without the version segment.
     */
    public function apiUrl(): string
    {
        if ($this->baseUrl !== null) {
            return rtrim($this->baseUrl, '/');
        }

        return $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * URL of the widget script of the environment, unless overridden in the widget options.
     */
    public function widgetScriptUrl(): string
    {
        $override = self::string($this->widget['script_url'] ?? null);

        if ($override !== null) {
            return $override;
        }

        return $this->sandbox ? self::SANDBOX_WIDGET_SCRIPT : self::PRODUCTION_WIDGET_SCRIPT;
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function positive(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private static function natural(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : $default;
    }
}
