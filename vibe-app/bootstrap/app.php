<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStationConfirmed;
use App\Http\Middleware\EnsureSubscriptionAllowsWrites;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'subscription.writes' => EnsureSubscriptionAllowsWrites::class,
        ]);
        // Behind a Cloudflare Tunnel the app is reached over plain http from this computer; trust the forwarded
        // headers so links are https, cookies are secure and the visitor's own address is recorded.
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', EnsureAccountActive::class);
        $middleware->appendToGroup('web', EnsureStationConfirmed::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
