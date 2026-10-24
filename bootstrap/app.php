<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['password.changed' => EnsurePasswordChanged::class]);

        // There is no route named "login" (the login screen is part of the /app SPA), so
        // Laravel's default redirect for guests threw a 500. API calls get a JSON 401
        // instead; anything else is sent to the SPA's login page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers in JSON, even if the caller (the dashboard's fetch calls,
        // for one) didn't send an `Accept: application/json` header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
