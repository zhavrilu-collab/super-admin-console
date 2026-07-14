<?php

namespace App\Providers;

use App\Models\Application;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Admin\SubscriptionPlanService;
use App\Support\AdminSession;
use App\Support\TwoFactorSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        $this->configureRateLimiting();
        $this->configureProductionSecurity();

        if (Schema::hasTable('settings')) {
            app(ConsoleSettingsService::class)->applyMailConfiguration();
        }

        View::composer('layouts.admin', function ($view): void {
            if (! auth()->check() || ! auth()->user()->isSuperAdmin()) {
                return;
            }

            $applications = Application::query()->orderBy('name')->get();
            $activeId = session(AdminSession::ACTIVE_APP_ID);
            $activeApplication = $activeId !== null
                ? $applications->firstWhere('id', (int) $activeId)
                : $applications->first();

            $view->with([
                'applications' => $applications,
                'activeApplication' => $activeApplication,
                'subscriptionPlans' => $activeApplication !== null
                    ? app(SubscriptionPlanService::class)->forApplication($activeApplication->id)
                    : collect(),
            ]);
        });
    }

    private function configureProductionSecurity(): void
    {
        if (config('security.force_https')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            $loginId = $request->session()->get(TwoFactorSession::LOGIN_USER_ID, 'guest');

            return Limit::perMinute(10)->by($loginId.'|'.$request->ip());
        });

        RateLimiter::for('admin-sync', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin-moderation', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('platform-auth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
