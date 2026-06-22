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
        'enabled' => filter_var(env('MICROSOFT_SSO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/microsoft/callback'),
        'tenant_allow_list' => array_values(array_filter(array_map('trim', explode(',', (string) env('MICROSOFT_ALLOWED_TENANT_IDS', ''))))),
        'domain_allow_list' => array_values(array_filter(array_map('trim', explode(',', (string) env('MICROSOFT_ALLOWED_DOMAINS', ''))))),
        // Docker Compose may pass MICROSOFT_JWKS_URL="" when unset; treat empty as missing.
        'jwks_url' => (static function (): string {
            $configured = trim((string) env('MICROSOFT_JWKS_URL', ''));

            if ($configured !== '') {
                return $configured;
            }

            $tenantId = trim((string) env('MICROSOFT_TENANT_ID', ''));

            if ($tenantId === '') {
                $allowedTenants = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('MICROSOFT_ALLOWED_TENANT_IDS', ''))
                )));
                $tenantId = $allowedTenants[0] ?? '';
            }

            if ($tenantId !== '') {
                return "https://login.microsoftonline.com/{$tenantId}/discovery/v2.0/keys";
            }

            return 'https://login.microsoftonline.com/common/discovery/v2.0/keys';
        })(),
        'issuer_prefix' => env('MICROSOFT_ISSUER_PREFIX', 'https://login.microsoftonline.com'),
    ],

    'microsoft_graph' => [
        'client_id' => env('MS_GRAPH_CLIENT_ID'),
        'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
        'tenant' => env('MS_GRAPH_TENANT_ID'),
        'mail_from' => env('MS_GRAPH_MAIL_FROM'),
    ],

    'onfon' => [
        'url' => env('ONFON_URL', ''),
        'access_key' => env('ONFON_ACCESS_KEY', ''),
        'api_key' => env('ONFON_API_KEY', ''),
        'client_id' => env('ONFON_CLIENT_ID', ''),
        'sender_id' => env('ONFON_SENDER_ID', ''),
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
