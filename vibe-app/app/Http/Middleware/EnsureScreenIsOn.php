<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the master user has turned the screen off, every visitor and every user except the master sees "Link is not available".
 * The master signs in at /login?master (the plain /login says the same as every other link) and turns the screen back on; sign-in itself, the health check and sign-out stay open.
 */
class EnsureScreenIsOn
{
    public function handle(Request $request, Closure $next): Response
    {
        $masterEntry = ($request->isMethod('GET') && $request->is('login') && $request->has('master')) || ($request->isMethod('POST') && $request->is('login'));

        if (! SystemSetting::screenIsOff() || $request->user()?->isMaster() || $masterEntry || $request->is('logout', 'up')) {
            return $next($request);
        }

        return response()->view('screen-off', [], 503);
    }
}
