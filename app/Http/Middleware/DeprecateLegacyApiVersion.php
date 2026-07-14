<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeprecateLegacyApiVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/v1*')) {
            $path = $request->path();
            $suffix = str_starts_with($path, 'api/') ? substr($path, 4) : $path;

            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Link', '</api/v1/'.$suffix.'>; rel="successor-version"');
        }

        return $response;
    }
}
