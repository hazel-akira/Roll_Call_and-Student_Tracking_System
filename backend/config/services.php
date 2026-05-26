<?php

return [
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

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'tenant_allow_list' => array_values(array_filter(array_map('trim', explode(',', (string) env('MICROSOFT_ALLOWED_TENANT_IDS', ''))))),
        'domain_allow_list' => array_values(array_filter(array_map('trim', explode(',', (string) env('MICROSOFT_ALLOWED_DOMAINS', ''))))),
        'jwks_url' => env('MICROSOFT_JWKS_URL', 'https://login.microsoftonline.com/common/discovery/v2.0/keys'),
        'issuer_prefix' => env('MICROSOFT_ISSUER_PREFIX', 'https://login.microsoftonline.com'),
    ],

    'dynamics' => [
        'base_url' => env('DYNAMICS_BASE_URL'),
        'tenant_id' => env('DYNAMICS_TENANT_ID'),
        'client_id' => env('DYNAMICS_CLIENT_ID'),
        'client_secret' => env('DYNAMICS_CLIENT_SECRET'),
        'scope' => env('DYNAMICS_SCOPE'),
        'attendance_endpoint' => env('DYNAMICS_ATTENDANCE_ENDPOINT', '/attendance-sync'),
        'timeout' => (int) env('DYNAMICS_TIMEOUT', 15),
    ],
];
