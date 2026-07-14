<?php

use App\Http\Controllers\Api\ApiV1DocumentationController;
use App\Http\Controllers\Api\Auth\PlatformAuthController;
use App\Http\Controllers\Api\PlatformAccountErasureController;
use App\Http\Controllers\Api\PlatformBillingController;
use App\Http\Controllers\Api\PlatformCustomerWebhookController;
use App\Http\Controllers\Api\PlatformGdprExportController;
use App\Http\Controllers\Api\PlatformImpersonationController;
use App\Http\Controllers\Api\PlatformInviteController;
use App\Http\Controllers\Api\PlatformUserImportController;
use App\Http\Controllers\Api\PlatformWorkspaceController;
use App\Http\Controllers\Api\Sync\SubscriptionPlanSyncController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\Webhooks\TenantWebhookController;
use App\Http\Middleware\AuthenticatePlatformToken;
use App\Http\Middleware\VerifySaasWebhookSecret;
use Illuminate\Support\Facades\Route;

/** @var string $routeNamePrefix */
$routeNamePrefix ??= 'api.';

Route::get('openapi.json', [ApiV1DocumentationController::class, 'show'])
    ->name($routeNamePrefix.'openapi');

Route::middleware('throttle:platform-auth')
    ->prefix('auth')
    ->name($routeNamePrefix.'auth.')
    ->group(function () use ($routeNamePrefix) {
        Route::post('login', [PlatformAuthController::class, 'login'])->name('login');
        Route::post('register', [PlatformAuthController::class, 'register'])->name('register');

        Route::middleware([AuthenticatePlatformToken::class])
            ->group(function () use ($routeNamePrefix) {
                Route::get('me', [PlatformAuthController::class, 'me'])->name('me');
                Route::post('logout', [PlatformAuthController::class, 'logout'])->name('logout');
                Route::get('data-export', [PlatformGdprExportController::class, 'export'])
                    ->name('data-export');
                Route::get('account-deletion', [PlatformAccountErasureController::class, 'show'])
                    ->name('account-deletion.show');
                Route::post('account-deletion', [PlatformAccountErasureController::class, 'store'])
                    ->name('account-deletion.store');
                Route::delete('account-deletion', [PlatformAccountErasureController::class, 'destroy'])
                    ->name('account-deletion.destroy');
            });
    });

Route::middleware([AuthenticatePlatformToken::class, 'throttle:platform-auth'])
    ->prefix('platform/workspaces')
    ->name($routeNamePrefix.'platform.workspaces.')
    ->group(function () {
        Route::get('/', [PlatformWorkspaceController::class, 'index'])->name('index');
    });

Route::middleware('throttle:platform-auth')
    ->prefix('platform/invites')
    ->name($routeNamePrefix.'platform.invites.')
    ->group(function () {
        Route::get('{token}', [PlatformInviteController::class, 'show'])->name('show');
        Route::post('{token}/accept', [PlatformInviteController::class, 'accept'])->name('accept');
    });

Route::middleware('throttle:platform-auth')
    ->prefix('platform/impersonation')
    ->name($routeNamePrefix.'platform.impersonation.')
    ->group(function () {
        Route::get('{token}', [PlatformImpersonationController::class, 'show'])->name('show');
        Route::post('{token}/end', [PlatformImpersonationController::class, 'end'])->name('end');
    });

Route::middleware('throttle:webhooks')
    ->post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name($routeNamePrefix.'webhooks.stripe');

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('platform')
    ->name($routeNamePrefix.'platform.')
    ->group(function () {
        Route::post('users/import', [PlatformUserImportController::class, 'store'])
            ->name('users.import');

        Route::get('invites', [PlatformInviteController::class, 'index'])
            ->name('invites.index');

        Route::post('invites', [PlatformInviteController::class, 'store'])
            ->name('invites.store');

        Route::post('memberships/sync', [PlatformWorkspaceController::class, 'sync'])
            ->name('memberships.sync');

        Route::post('billing/checkout', [PlatformBillingController::class, 'checkout'])
            ->name('billing.checkout');

        Route::post('billing/portal', [PlatformBillingController::class, 'portal'])
            ->name('billing.portal');

        Route::get('customer-webhooks', [PlatformCustomerWebhookController::class, 'index'])
            ->name('customer-webhooks.index');

        Route::post('customer-webhooks', [PlatformCustomerWebhookController::class, 'store'])
            ->name('customer-webhooks.store');

        Route::delete('customer-webhooks/{webhookId}', [PlatformCustomerWebhookController::class, 'destroy'])
            ->name('customer-webhooks.destroy');

        Route::post('events/dispatch', [PlatformCustomerWebhookController::class, 'dispatch'])
            ->name('events.dispatch');
    });

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('sync')
    ->name($routeNamePrefix.'sync.')
    ->group(function () {
        Route::get('subscription-plans', [SubscriptionPlanSyncController::class, 'index'])
            ->name('subscription-plans.index');
    });

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('webhooks')
    ->name($routeNamePrefix.'webhooks.')
    ->group(function () {
        Route::post('tenants/registered', [TenantWebhookController::class, 'registered'])
            ->name('tenants.registered');
    });
