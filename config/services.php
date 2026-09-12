<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'garmin' => [
        'python_binary' => env('PYTHON_BINARY', '/usr/bin/python3'),
    ],

    /*
     * Suunto Cloud API (apizone.suunto.com).
     *
     * Beda dari Garmin: tidak ada skrip Python, semuanya OAuth2 HTTP dari PHP.
     * `subscription_key` berasal dari langganan "Developer API" di portal Suunto;
     * `client_id`/`client_secret` dari OAuth settings di profil portal.
     */
    'suunto' => [
        'client_id' => env('SUUNTO_CLIENT_ID'),
        'client_secret' => env('SUUNTO_CLIENT_SECRET'),
        'subscription_key' => env('SUUNTO_SUBSCRIPTION_KEY'),
        'redirect' => env('SUUNTO_REDIRECT_URI', '/settings/suunto/callback'),
        'oauth_base' => env('SUUNTO_OAUTH_BASE', 'https://cloudapi-oauth.suunto.com'),
        'api_base' => env('SUUNTO_API_BASE', 'https://cloudapi.suunto.com'),
        'api_path' => env('SUUNTO_API_PATH', '/v3/workouts'),
        'timeout' => (int) env('SUUNTO_TIMEOUT', 30),
    ],

];
