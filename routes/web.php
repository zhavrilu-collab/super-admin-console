<?php

use App\Http\Controllers\Admin\AdminApplicationController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminAppController;
use App\Http\Controllers\Admin\AdminBillingDashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSuperAdminController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSyncController;
use App\Http\Controllers\Admin\AdminTenantImpersonationController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PlatformOAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:platform-auth')->group(function () {
    Route::get('/auth/google/redirect', [PlatformOAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [PlatformOAuthController::class, 'handleGoogleCallback'])
        ->name('auth.google.callback');

    Route::get('/auth/microsoft/redirect', [PlatformOAuthController::class, 'redirectToMicrosoft'])
        ->name('auth.microsoft.redirect');

    Route::get('/auth/microsoft/callback', [PlatformOAuthController::class, 'handleMicrosoftCallback'])
        ->name('auth.microsoft.callback');
});

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/sigurnost', [AdminTwoFactorController::class, 'index'])->name('two-factor.index');
        Route::post('/sigurnost/pokreni', [AdminTwoFactorController::class, 'beginSetup'])->name('two-factor.begin');
        Route::post('/sigurnost/potvrdi', [AdminTwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('/sigurnost', [AdminTwoFactorController::class, 'destroy'])->name('two-factor.destroy');
    });

Route::middleware(['auth', 'admin', 'require-super-admin-2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/naplata', [AdminBillingDashboardController::class, 'index'])->name('billing.index');
        Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit.index');
        Route::post('/sync', [AdminSyncController::class, 'pull'])
            ->middleware('throttle:admin-sync')
            ->name('sync');
        Route::post('/switch-app/{application}', [AdminAppController::class, 'switchApp'])->name('switch-app');
        Route::patch('/tenants/bulk-status', [AdminTenantController::class, 'bulkUpdateStatus'])
            ->middleware('throttle:admin-moderation')
            ->name('tenants.bulk-update-status');
        Route::get('/tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
        Route::patch('/tenants/{tenant}/status', [AdminTenantController::class, 'updateStatus'])
            ->middleware('throttle:admin-moderation')
            ->name('tenants.update-status');
        Route::patch('/tenants/{tenant}/plan', [AdminTenantController::class, 'updatePlan'])
            ->middleware('throttle:admin-moderation')
            ->name('tenants.update-plan');
        Route::post('/tenants/{tenant}/impersonate', [AdminTenantImpersonationController::class, 'store'])
            ->middleware('throttle:admin-moderation')
            ->name('tenants.impersonate');

        Route::get('/postavke', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/postavke/mail', [AdminSettingsController::class, 'updateMail'])->name('settings.mail');

        Route::resource('aplikacije', AdminApplicationController::class)
            ->parameters(['aplikacije' => 'application'])
            ->names('applications')
            ->except(['show']);

        Route::resource('paketi', AdminSubscriptionPlanController::class)
            ->parameters(['paketi' => 'subscriptionPlan'])
            ->names('subscription-plans')
            ->except(['show']);

        Route::resource('super-admini', AdminSuperAdminController::class)
            ->parameters(['super-admini' => 'superAdmin'])
            ->names('super-admins')
            ->except(['show']);
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
