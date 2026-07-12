<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\RequireSuperAdminTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'require-super-admin-2fa' => RequireSuperAdminTwoFactor::class,
        ]);

        $middleware->append([
            ForceHttps::class,
            SecurityHeaders::class,
        ]);

        $trustedProxies = env('TRUSTED_PROXIES');

        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
            );
        }

        $middleware->redirectUsersTo(function (): string {
            $user = auth()->user();

            if ($user instanceof User && $user->isSuperAdmin()) {
                if (config('security.require_super_admin_two_factor', true) && ! $user->hasTwoFactorEnabled()) {
                    return route('admin.two-factor.index', absolute: false);
                }

                return route('admin.dashboard', absolute: false);
            }

            return route('dashboard', absolute: false);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
