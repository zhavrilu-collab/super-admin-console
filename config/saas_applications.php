<?php

return [

    'drivers' => [
        'udruga_saas' => [
            'label' => 'Udruga SaaS API (standardni)',
            'class' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
        ],
    ],

    'applications' => [

        'udruga-saas' => [
            'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
            'base_url' => env('UDRUGA_SAAS_API_URL', 'http://127.0.0.1:8000'),
            'api_key' => env('UDRUGA_SAAS_API_KEY'),
        ],

    ],

];
