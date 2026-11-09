<?php

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
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO,
        );

        // A closure because config is not loaded yet. Laravel treats each host as a
        // pattern, and skips this check in the local environment and in tests.
        $middleware->trustHosts(
            at: fn () => array_map(
                fn (string $host) => '^'.preg_quote($host).'$',
                config('security.trusted_hosts'),
            ),
            subdomains: false,
        );

        // Outermost, so responses rejected by other middleware carry the headers too.
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);

        // First in the group so login redirects carry the header too.
        $middleware->web(prepend: \App\Http\Middleware\NoIndexPrivatePages::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
