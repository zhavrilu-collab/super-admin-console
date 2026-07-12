<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_super_admin_two_factor', true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('admin.two-factor.*')) {
            return $next($request);
        }

        return redirect()
            ->route('admin.two-factor.index')
            ->with('warning', 'Dvofaktorska autentifikacija je obavezna prije pristupa konzoli.');
    }
}
