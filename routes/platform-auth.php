<?php

use App\Http\Controllers\Auth\PlatformLoginController;
use App\Http\Controllers\Auth\PlatformTwoFactorChallengeController;
use App\Http\Controllers\Auth\PlatformTwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'throttle:platform-auth'])->group(function () {
    Route::get('/platform/prijava', [PlatformLoginController::class, 'create'])
        ->name('platform.login');

    Route::post('/platform/prijava', [PlatformLoginController::class, 'store'])
        ->name('platform.login.store');

    Route::get('/platform/prijava/nastavak', [PlatformLoginController::class, 'continue'])
        ->name('platform.login.continue');
});

Route::middleware('throttle:two-factor')->group(function () {
    Route::get('/platform/prijava/2fa', [PlatformTwoFactorChallengeController::class, 'create'])
        ->name('platform.two-factor.login');

    Route::post('/platform/prijava/2fa', [PlatformTwoFactorChallengeController::class, 'store'])
        ->name('platform.two-factor.login.store');
});

Route::middleware('throttle:platform-auth')->group(function () {
    Route::get('/platform/odabir', [PlatformLoginController::class, 'pick'])
        ->name('platform.pick');

    Route::post('/platform/odabir', [PlatformLoginController::class, 'storePick'])
        ->name('platform.pick.store');

    Route::get('/platform/sigurnost', [PlatformTwoFactorSetupController::class, 'index'])
        ->name('platform.two-factor.setup');

    Route::post('/platform/sigurnost/pokreni', [PlatformTwoFactorSetupController::class, 'begin'])
        ->name('platform.two-factor.setup.begin');

    Route::post('/platform/sigurnost/potvrdi', [PlatformTwoFactorSetupController::class, 'confirm'])
        ->name('platform.two-factor.setup.confirm');

    Route::delete('/platform/sigurnost', [PlatformTwoFactorSetupController::class, 'destroy'])
        ->name('platform.two-factor.setup.destroy');
});
