<?php

use App\Http\Controllers\Api\Auth\PlatformAuthController;
use App\Http\Controllers\Api\PlatformImpersonationController;
use App\Http\Controllers\Api\PlatformInviteController;
use App\Http\Controllers\Api\PlatformUserImportController;
use App\Http\Controllers\Api\PlatformWorkspaceController;
use App\Http\Controllers\Api\Sync\SubscriptionPlanSyncController;
use App\Http\Controllers\Api\Webhooks\TenantWebhookController;
use App\Http\Middleware\AuthenticatePlatformToken;
use App\Http\Middleware\VerifySaasWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:platform-auth')
    ->prefix('auth')
    ->name('api.auth.')
    ->group(function () {
        Route::post('login', [PlatformAuthController::class, 'login'])->name('login');

        Route::middleware([AuthenticatePlatformToken::class])
            ->group(function () {
                Route::get('me', [PlatformAuthController::class, 'me'])->name('me');
                Route::post('logout', [PlatformAuthController::class, 'logout'])->name('logout');
            });
    });

Route::middleware([AuthenticatePlatformToken::class, 'throttle:platform-auth'])
    ->prefix('platform/workspaces')
    ->name('api.platform.workspaces.')
    ->group(function () {
        Route::get('/', [PlatformWorkspaceController::class, 'index'])->name('index');
    });

Route::middleware('throttle:platform-auth')
    ->prefix('platform/invites')
    ->name('api.platform.invites.')
    ->group(function () {
        Route::get('{token}', [PlatformInviteController::class, 'show'])->name('show');
        Route::post('{token}/accept', [PlatformInviteController::class, 'accept'])->name('accept');
    });

Route::middleware('throttle:platform-auth')
    ->prefix('platform/impersonation')
    ->name('api.platform.impersonation.')
    ->group(function () {
        Route::get('{token}', [PlatformImpersonationController::class, 'show'])->name('show');
        Route::post('{token}/end', [PlatformImpersonationController::class, 'end'])->name('end');
    });

Route::middleware([VerifySaasWebhookSecret::class, 'throttle:webhooks'])
    ->prefix('platform')
    ->name('api.platform.')
    ->group(function () {
        Route::post('users/import', [PlatformUserImportController::class, 'store'])
            ->name('users.import');

        Route::get('invites', [PlatformInviteController::class, 'index'])
            ->name('invites.index');

        Route::post('invites', [PlatformInviteController::class, 'store'])
            ->name('invites.store');

        Route::post('memberships/sync', [PlatformWorkspaceController::class, 'sync'])
            ->name('memberships.sync');
    });

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
