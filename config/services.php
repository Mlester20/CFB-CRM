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

    /*
    |--------------------------------------------------------------------------
    | SAIMS (read-only inventory and sales API)
    |--------------------------------------------------------------------------
    |
    | The CRM only ever READS from SAIMS, through App\Services\Saims\SaimsClient.
    | The token stays on the server: it is never shared with the browser.
    |
    */

    'saims' => [
        'enabled' => filter_var(env('SAIMS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'url' => env('SAIMS_URL'),
        'token' => env('SAIMS_TOKEN'),
        'timeout' => (int) env('SAIMS_TIMEOUT', 5),
        'cache_seconds' => (int) env('SAIMS_CACHE_SECONDS', 60),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
