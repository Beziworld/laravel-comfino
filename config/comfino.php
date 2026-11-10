<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Every connection is one ComfinoPay shop account. The secret "api_key"
    | authenticates server-to-server calls (Api-Key header) and verifies the
    | CR-Signature header of status notifications. The public "widget_key"
    | is used by the frontend widget; when it is empty it is fetched from the
    | API on demand. Sandbox and production keys are not interchangeable.
    |
    | Use Comfino::connection('name') for another shop and
    | Comfino::using([...]) for credentials stored at runtime, for example
    | per tenant. Options outside "connections" are shared by every
    | connection and can be overridden in a connection.
    |
    */

    'default' => env('COMFINO_CONNECTION', 'default'),

    'connections' => [

        'default' => [
            'api_key' => env('COMFINO_API_KEY'),
            'widget_key' => env('COMFINO_WIDGET_KEY'),
        ],

        // 'second-shop' => [
        //     'api_key' => env('COMFINO_SECOND_API_KEY'),
        //     'widget_key' => env('COMFINO_SECOND_WIDGET_KEY'),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The sandbox uses https://api-ecommerce.craty.pl, production
    | https://api-ecommerce.comfino.pl. "base_url" overrides both.
    |
    */

    'sandbox' => (bool) env('COMFINO_SANDBOX', true),

    'base_url' => env('COMFINO_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Defaults of new loan applications
    |--------------------------------------------------------------------------
    |
    | "return_url" receives the customer after the application form.
    | "notify_url" defaults to the package route of the connection.
    | "language" and "currency" are sent as Api-Language and Api-Currency.
    |
    */

    'return_url' => env('COMFINO_RETURN_URL'),

    'notify_url' => env('COMFINO_NOTIFY_URL'),

    'language' => env('COMFINO_LANGUAGE', 'pl'),

    'currency' => env('COMFINO_CURRENCY', 'PLN'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | Reads are retried "retries" times on connection errors and HTTP 5xx,
    | waiting "retry_delay" milliseconds in between. Writes (loan
    | applications, cancellations) are never retried. ComfinoPay recommends
    | a 5 second connection timeout for offers shown on product pages.
    |
    */

    'timeout' => (int) env('COMFINO_TIMEOUT', 15),

    'connect_timeout' => (int) env('COMFINO_CONNECT_TIMEOUT', 5),

    'retries' => (int) env('COMFINO_RETRIES', 1),

    'retry_delay' => (int) env('COMFINO_RETRY_DELAY', 300),

    'timezone' => env('COMFINO_TIMEZONE', 'Europe/Warsaw'),

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    |
    | Defaults of the product page widget rendered by Comfino::widget().
    | "type" is standard or classic, "embed_method" one of INSERT_INTO_FIRST,
    | INSERT_INTO_LAST, INSERT_BEFORE, INSERT_AFTER. "script_url" overrides
    | the CDN script of the environment.
    |
    */

    'widget' => [
        'script_url' => env('COMFINO_WIDGET_SCRIPT_URL'),
        'type' => env('COMFINO_WIDGET_TYPE', 'standard'),
        'embed_method' => env('COMFINO_WIDGET_EMBED_METHOD', 'INSERT_INTO_LAST'),
        'price_selector' => env('COMFINO_WIDGET_PRICE_SELECTOR'),
        'target_selector' => env('COMFINO_WIDGET_TARGET_SELECTOR'),
        'offer_types' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Route receiving status notifications: {path}/{connection?}, methods
    | PUT and POST. The CR-Signature header is verified with the API key of
    | the connection; forged notifications get HTTP 403. "allowed_ips"
    | optionally restricts senders (production 94.152.189.231; sandbox
    | 35.187.24.192, 35.205.155.59, 35.240.21.61, 34.77.240.220).
    | "deduplicate_ttl" (seconds, 0 disables) acknowledges a repeated
    | identical notification without dispatching the events again.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('COMFINO_NOTIFICATIONS_ENABLED', true),
        'path' => env('COMFINO_NOTIFICATION_PATH', 'comfino/notification'),
        'name' => 'comfino.notification',
        'middleware' => ['api'],
        'verify_signature' => (bool) env('COMFINO_VERIFY_SIGNATURE', true),
        'allowed_ips' => [],
        'deduplicate_ttl' => (int) env('COMFINO_DEDUPLICATE_TTL', 0),
    ],

];
