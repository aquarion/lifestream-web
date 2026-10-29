<?php

use App\Services\LifestreamApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The feed is a read-only POST from nicave.js, which sends no CSRF token.
        $middleware->validateCsrfTokens(except: ['feed', 'fetchNext.php']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // The Lifestream API being down or erroring is an upstream outage,
        // not a bug here: answer 503 (JSON for the endpoints nicave.js
        // calls). The exception is still reported as normal.
        $exceptions->render(function (LifestreamApiException $e, Request $request) {
            if ($request->is('feed', 'fetchNext.php', 'lastseen')) {
                return response()->json(['status' => 503, 'message' => 'The lifestream is unavailable right now.'], 503);
            }

            // The framework only registers the errors:: views while rendering
            // an HTTP exception, so do it here for its standard 503 page.
            (new RegisterErrorViewPaths)();

            return response()->view('errors::503', [], 503);
        });
    })->create();
