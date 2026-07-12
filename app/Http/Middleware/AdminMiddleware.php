<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Support\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403);
        }

        if (! $request->session()->has(AdminSession::ACTIVE_APP_ID)) {
            $firstAppId = Application::query()->orderBy('id')->value('id');

            if ($firstAppId !== null) {
                $request->session()->put(AdminSession::ACTIVE_APP_ID, $firstAppId);
            }
        }

        return $next($request);
    }
}
