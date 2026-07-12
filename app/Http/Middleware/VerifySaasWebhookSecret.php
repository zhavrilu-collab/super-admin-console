<?php

namespace App\Http\Middleware;

use App\Services\Admin\ConsoleSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySaasWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = app(ConsoleSettingsService::class)->webhookSecret();

        if (! is_string($configuredSecret) || $configuredSecret === '') {
            abort(503, 'Webhook nije konfiguriran.');
        }

        $providedSecret = $request->bearerToken();

        if ($providedSecret === null || ! hash_equals($configuredSecret, $providedSecret)) {
            abort(401, 'Neispravan webhook ključ.');
        }

        return $next($request);
    }
}
