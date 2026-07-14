<?php

namespace App\Http\Middleware;

use App\Services\Identity\PlatformAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlatformToken
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->platformAuth->authenticateRequestBearer($request->bearerToken());

        if ($user === null) {
            abort(401, 'Neispravan ili istekao platform token.');
        }

        return $next($request);
    }
}
