<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force API requests to be treated as JSON.
 *
 * Without an Accept header that includes "application/json", Laravel's
 * exception handling (e.g. AuthenticationException) tries to render an
 * HTML/redirect response and can crash on the missing "login" route.
 */
class ForceJsonResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
