<?php

declare(strict_types=1);

use Beziworld\Comfino\ComfinoConfig;
use Beziworld\Comfino\Enums\EmbedMethod;
use Beziworld\Comfino\Enums\LoanType;
use Beziworld\Comfino\Enums\WidgetType;
use Beziworld\Comfino\Facades\Comfino;
use Beziworld\Comfino\Tests\TestCase;
use Illuminate\Support\Facades\Http;

it('builds the widget configuration from defaults and options', function (): void {
    config()->set('comfino.connections.default.widget', ['price_selector' => 'span[itemprop=price]', 'target_selector' => 'div.product-actions']);
    Comfino::purge();

    $config = Comfino::widget()->config([
        'type' => WidgetType::Classic,
        'offerTypes' => [LoanType::InstallmentsZeroPercent, LoanType::PayLater],
        'embedMethod' => EmbedMethod::InsertAfter,
        'price' => 1499.99,
        'priceObserverSelector' => null,
    ]);

    expect($config)->toBe([
        'widgetKey' => TestCase::WIDGET_KEY,
        'priceSelector' => 'span[itemprop=price]',
        'widgetTargetSelector' => 'div.product-actions',
        'type' => 'classic',
        'offerTypes' => ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER'],
        'embedMethod' => 'INSERT_AFTER',
        'language' => 'pl',
        'currency' => 'PLN',
        'price' => 1499.99,
    ]);
});

it('uses the CDN script of the environment', function (): void {
    expect(Comfino::widget()->scriptUrl())->toBe(ComfinoConfig::SANDBOX_WIDGET_SCRIPT)
        ->and(Comfino::using(['api_key' => 'k', 'widget_key' => 'w', 'sandbox' => false])->widget()->scriptUrl())
        ->toBe('https://widget.comfino.pl/v2/widget-frontend.min.js')
        ->and(Comfino::using(['api_key' => 'k', 'widget_key' => 'w', 'widget' => ['script_url' => 'https://cdn.example.com/w.js']])->widget()->scriptUrl())
        ->toBe('https://cdn.example.com/w.js');
});

it('renders an escaped snippet with a CSP nonce', function (): void {
    $html = Comfino::widget()->snippet(['widgetTargetSelector' => '</script><script>alert(1)</script>'], nonce: 'abc"123');

    expect($html)->toStartWith('<script nonce="abc&quot;123">')
        ->and($html)->toContain('ComfinoWidgetFrontend.init(')
        ->and($html)->toContain('s.src="https://widget.craty.pl/v2/widget-frontend.min.js"')
        ->and($html)->toContain('"widgetKey":"' . TestCase::WIDGET_KEY . '"')
        ->and(substr_count($html, '</script>'))->toBe(1)
        ->and($html)->toContain('</script>');
});

it('fetches the widget key for the widget when none is configured', function (): void {
    Http::fake(['*/v1/widget-key' => Http::response('"api-widget-key"')]);

    expect(Comfino::connection('second')->widget()->config()['widgetKey'])->toBe('api-widget-key')
        ->and(Comfino::connection('second')->widget('explicit')->config()['widgetKey'])->toBe('explicit');
});

it('generates the paywall authentication key like the official client', function (): void {
    $timestamp = 1762770000;
    $key = Comfino::paywallAuthKey($timestamp);
    $decoded = base64_decode($key, true);

    expect($decoded)->not->toBeFalse()
        ->and(unpack('J', substr($decoded, 0, 8))[1])->toBe($timestamp)
        ->and(substr($decoded, 8, 32))->toBe(hash_hmac('sha3-256', pack('J', $timestamp) . TestCase::WIDGET_KEY, TestCase::API_KEY, true))
        ->and(substr($decoded, 40))->toBe(TestCase::WIDGET_KEY);
});
