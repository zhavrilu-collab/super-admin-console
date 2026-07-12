<?php

use App\Http\Controllers\Api\Sync\SubscriptionPlanSyncController;
use App\Http\Controllers\Api\Webhooks\TenantWebhookController;
use App\Http\Middleware\VerifySaasWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('sync')
    ->name('api.sync.')
    ->group(function () {
        Route::get('subscription-plans', [SubscriptionPlanSyncController::class, 'index'])
            ->name('subscription-plans.index');
    });

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('webhooks')
    ->name('api.webhooks.')
    ->group(function () {
        Route::post('tenants/registered', [TenantWebhookController::class, 'registered'])
            ->name('tenants.registered');
    });
