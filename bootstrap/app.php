<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hosted behind a TLS-terminating nginx reverse proxy that
        // forwards to FrankenPHP on loopback. Trust the proxy so
        // X-Forwarded-Proto/Host are honored — otherwise Laravel sees
        // plain http and Livewire builds http:// endpoints that the
        // https page blocks. Safe to trust any proxy here: FrankenPHP
        // only listens on 127.0.0.1, so nginx is the only client.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
