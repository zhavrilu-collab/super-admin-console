<?php

return [

    /*
    | When false, outbound HTTP to SaaS modules skips TLS verification.
    | Needed on hosts where public hostname hairpins through an SSL-inspecting gateway.
    */
    'http_verify' => filter_var(env('SAAS_HTTP_VERIFY', true), FILTER_VALIDATE_BOOL),

    /*
    | Force SaaS HTTPS calls to resolve to 127.0.0.1 (same-VPS, bypass hairpin/WAF).
    */
    'http_resolve_loopback' => filter_var(env('SAAS_HTTP_RESOLVE_LOOPBACK', false), FILTER_VALIDATE_BOOL),

    'drivers' => [
        'udruga_saas' => [
            'label' => 'Udruga SaaS API (standardni)',
            'class' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
        ],
        'hr_saas' => [
            'label' => 'HR SaaS API (standardni)',
            'class' => \App\Services\Admin\Sync\HrSaasSyncDriver::class,
        ],
        'legal_saas' => [
            'label' => 'Legal SaaS API (standardni)',
            'class' => \App\Services\Admin\Sync\LegalSaasSyncDriver::class,
        ],
    ],

    'applications' => [

        'udruga-saas' => [
            'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
            'base_url' => env('UDRUGA_SAAS_API_URL', 'http://127.0.0.1:8000'),
            'api_key' => env('UDRUGA_SAAS_API_KEY'),
        ],

        'hr-saas' => [
            'driver' => \App\Services\Admin\Sync\HrSaasSyncDriver::class,
            'base_url' => env('HR_SAAS_API_URL', 'http://127.0.0.1:8004'),
            'api_key' => env('HR_SAAS_API_KEY'),
        ],

        'legal-saas' => [
            'driver' => \App\Services\Admin\Sync\LegalSaasSyncDriver::class,
            'base_url' => env('LEGAL_SAAS_API_URL', 'http://127.0.0.1:8006'),
            'api_key' => env('LEGAL_SAAS_API_KEY'),
        ],

    ],

];
