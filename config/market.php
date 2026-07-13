<?php

return [
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
