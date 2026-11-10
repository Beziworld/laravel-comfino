<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Support;

use BackedEnum;
use Beziworld\Comfino\ComfinoConfig;

/**
 * Builder of the ComfinoPay product page widget (ComfinoWidgetFrontend.init).
 *
 * The widget key is public, so the configuration and the snippet are safe
 * to render in HTML. Options use the names of the widget documentation
 * (widgetKey, priceSelector, widgetTargetSelector, priceObserverSelector,
 * priceObserverLevel, type, offerTypes, embedMethod, price, productId,
 * numOfInstallments, language, currency, showProviderLogos, hasPriceInput,
 * customBannerCss, customCalculatorCss, debugMode and others); enum cases
 * are converted to their values and null options are dropped.
 */
final class Widget
{
    public function __construct(
        private readonly ComfinoConfig $config,
        private readonly ?string $widgetKey,
    ) {}

    public function scriptUrl(): string
    {
        return $this->config->widgetScriptUrl();
    }

    /**
     * Initialisation options: configured defaults merged with the given options.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function config(array $options = []): array
    {
        $defaults = $this->config->widget;
        $offerTypes = $defaults['offer_types'] ?? null;

        $config = [
            'widgetKey' => $this->widgetKey,
            'priceSelector' => $defaults['price_selector'] ?? null,
            'widgetTargetSelector' => $defaults['target_selector'] ?? null,
            'type' => $defaults['type'] ?? null,
            'offerTypes' => is_array($offerTypes) && $offerTypes !== [] ? $offerTypes : null,
            'embedMethod' => $defaults['embed_method'] ?? null,
            'language' => $this->config->language,
            'currency' => $this->config->currency,
        ];

        foreach ($options as $key => $value) {
            $config[$key] = $value;
        }

        return array_filter(self::normalize($config), static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * HTML snippet that loads the widget script and initialises the widget.
     *
     * @param array<string, mixed> $options
     * @param string|null $nonce Content Security Policy nonce of the inline script.
     */
    public function snippet(array $options = [], ?string $nonce = null): string
    {
        $json = json_encode(
            (object) $this->config($options),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
        $url = json_encode($this->scriptUrl(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $nonceAttribute = $nonce !== null ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5) . '"' : '';

        return '<script' . $nonceAttribute . '>(function(){var s=document.createElement("script");'
            . 's.onload=function(){ComfinoWidgetFrontend.init(' . $json . ');};'
            . 's.src=' . $url . ';s.async=true;document.getElementsByTagName("head")[0].appendChild(s);})();</script>';
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private static function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof BackedEnum) {
                $values[$key] = $value->value;
            } elseif (is_array($value)) {
                $values[$key] = self::normalize($value);
            }
        }

        return $values;
    }
}
