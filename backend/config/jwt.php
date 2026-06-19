<?php

return [
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'roll-call-api')),
    'ttl' => (int) env('JWT_TTL', 3600),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600),
    'algo' => env('JWT_ALGO', 'HS256'),
];
