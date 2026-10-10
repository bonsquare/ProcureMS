<?php

use App\Http\Middleware\EnforceSubMasterLimits;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureDriveConnected;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStationConfirmed;
use App\Http\Middleware\EnsureSubscriptionAllowsWrites;
use App\Http\Middleware\ShowDriveReconnectBanner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'drive.connected' => EnsureDriveConnected::class,
            'subscription.writes' => EnsureSubscriptionAllowsWrites::class,
        ]);
        // Behind a Cloudflare Tunnel the app is reached over plain http from this computer; trust the forwarded
        // headers so links are https, cookies are secure and the visitor's own address is recorded.
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', EnsureAccountActive::class);
        $middleware->appendToGroup('web', EnsureStationConfirmed::class);
        $middleware->appendToGroup('web', ShowDriveReconnectBanner::class);
        // The Sub-master limits must run before route-model binding, so a refusal never depends on the record existing.
        $middleware->appendToGroup('web', EnforceSubMasterLimits::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnforceSubMasterLimits::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
