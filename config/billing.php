<?php

return [

    'currency' => env('BILLING_CURRENCY', 'eur'),

    /*
    |--------------------------------------------------------------------------
    | Stripe keys (fallback when not stored in console settings)
    |--------------------------------------------------------------------------
    */
    'stripe_secret_key' => env('STRIPE_SECRET_KEY'),
    'stripe_publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Dunning (neuspjela uplata)
    |--------------------------------------------------------------------------
    */
    'dunning' => [
        'reminder_days' => [3, 5, 7],
        'suspend_after_days' => (int) env('BILLING_DUNNING_SUSPEND_DAYS', 14),
        'downgrade_on_suspend' => (bool) env('BILLING_DUNNING_DOWNGRADE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proration pri promjeni paketa usred ciklusa
    |--------------------------------------------------------------------------
    */
    'proration_behavior' => env('BILLING_PRORATION_BEHAVIOR', 'create_prorations'),

    'metrics' => [
        'default_ltv_months' => (int) env('BILLING_LTV_MONTHS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uplata na poslovni račun (udruga / B2B)
    |--------------------------------------------------------------------------
    */
    'bank_transfer' => [
        'enabled' => (bool) env('BILLING_BANK_TRANSFER_ENABLED', true),
        'recipient_name' => env('BILLING_BANK_RECIPIENT', 'Udruga SaaS d.o.o.'),
        'iban' => env('BILLING_BANK_IBAN'),
        'payment_days' => (int) env('BILLING_BANK_PAYMENT_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Checkout — načini plaćanja
    |--------------------------------------------------------------------------
    */
    'checkout_payment_methods' => ['card', 'sepa_debit'],

];
