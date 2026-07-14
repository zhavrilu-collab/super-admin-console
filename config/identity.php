<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform access tokens
    |--------------------------------------------------------------------------
    */
    'token_ttl_hours' => (int) env('PLATFORM_TOKEN_TTL_HOURS', 720),

    /*
    |--------------------------------------------------------------------------
    | Allowed module origins (CORS preflight for auth API, optional)
    |--------------------------------------------------------------------------
    */
    'allowed_module_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLATFORM_ALLOWED_MODULE_ORIGINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Faza D)
    |--------------------------------------------------------------------------
    */
    'google_oauth_enabled' => (bool) env('GOOGLE_OAUTH_ENABLED', false),

];
