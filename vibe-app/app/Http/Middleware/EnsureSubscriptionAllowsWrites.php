<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAllowsWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('logout') || $request->user()?->role === 'master_user') {
            return $next($request);
        }

        $subscription = $request->user()?->activeSubscription();
        abort_unless($subscription?->allowsTransactions(), 403, 'Your organization subscription is read-only. Contact the system administrator to renew or reactivate it.');

        return $next($request);
    }
}
