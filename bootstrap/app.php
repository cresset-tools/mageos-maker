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
        // forwards to FrankenPHP on loopback. Trust just that loopback
        // peer so X-Forwarded-Proto/Host are honored — otherwise Laravel
        // sees plain http and Livewire builds http:// endpoints the https
        // page blocks. Scoped to loopback (not `*`) so forwarded headers
        // are never trusted from anywhere but nginx.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
