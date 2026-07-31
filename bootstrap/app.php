<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\ForceJsonResponses::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The default HandleCors middleware only runs for requests that complete
        // normally. Responses rendered for exceptions bypass it, so they would
        // be missing Access-Control-Allow-Origin and get blocked by the browser
        // before the client can read them. Attach the headers to every rendered
        // exception response here instead.
        $exceptions->respond(function ($response, \Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return $response;
            }

            $origin = $request->headers->get('Origin');

            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, Accept');
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            }

            return $response;
        });
    })->create();
