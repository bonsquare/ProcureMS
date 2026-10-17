<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // "a|b" lets a role that has either permission through.
        abort_unless(collect(explode('|', $permission))->contains(fn (string $one) => $request->user()?->hasPermission($one)), 403, 'Your role does not have permission to perform this action.');

        return $next($request);
    }
}
