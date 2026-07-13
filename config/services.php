<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram_bot' => [
        'base_url' => env('TELEGRAM_BOT_BASE_URL', 'https://api.telegram.org'),
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_BOT_CHAT_ID'),
        'timeout' => (int) env('TELEGRAM_BOT_TIMEOUT', 10),
    ],

    'bale_bot' => [
        'base_url' => env('BALE_BOT_BASE_URL', 'https://tapi.bale.ai'),
        'token' => env('BALE_BOT_TOKEN'),
        'chat_id' => env('BALE_BOT_CHAT_ID'),
        'timeout' => (int) env('BALE_BOT_TIMEOUT', 10),
    ],

];
