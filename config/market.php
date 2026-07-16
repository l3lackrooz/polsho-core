<?php

return [
    'alerts' => [
        // A second freshness guard protects queued alert evaluation from an
        // aggregate that sat behind a busy worker. Reference rates are allowed
        // a longer window only for alerts explicitly scoped to that provider.
        'max_quote_age_seconds' => env('MARKET_ALERT_MAX_QUOTE_AGE_SECONDS', 60),
        'max_reference_quote_age_seconds' => env('MARKET_ALERT_MAX_REFERENCE_QUOTE_AGE_SECONDS', 1800),
    ],
    'providers' => [
        'sample_exchange' => [
            'enabled' => env('MARKET_SAMPLE_EXCHANGE_ENABLED', true),
            'rest' => [
                'base_url' => env('MARKET_SAMPLE_EXCHANGE_REST_URL', 'https://api.example.com'),
                'timeout' => env('MARKET_SAMPLE_EXCHANGE_REST_TIMEOUT', 10),
            ],
            'websocket' => [
                'url' => env('MARKET_SAMPLE_EXCHANGE_WS_URL', 'wss://stream.example.com/ws'),
            ],
        ],
    ],
];
