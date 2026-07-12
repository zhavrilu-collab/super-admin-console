<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS URLs
    |--------------------------------------------------------------------------
    |
    | When enabled, all generated URLs use https:// and insecure requests may
    | be redirected to HTTPS (see ForceHttps middleware).
    |
    */

    'force_https' => (bool) env('APP_FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs or "*" when behind a load balancer / reverse
    | proxy (nginx, Cloudflare, etc.). Required for correct HTTPS detection.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Mandatory 2FA for super-admins
    |--------------------------------------------------------------------------
    |
    | When enabled, super-admins must configure 2FA before accessing /admin.
    | Set REQUIRE_SUPER_ADMIN_2FA=false in local .env to skip during development.
    |
    */

    'require_super_admin_two_factor' => (bool) env('REQUIRE_SUPER_ADMIN_2FA', true),

];
