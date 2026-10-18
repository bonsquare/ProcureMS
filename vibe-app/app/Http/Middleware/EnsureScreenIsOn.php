<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\ScreenSections;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the master user has turned the screen off, every visitor and every user except the master sees the "System Under Maintenance" page.
 * A tab the master has switched off shows "Under Maintenance" for that tab only.
 * The master signs in at /login?master (the plain /login says the same as every other link) and turns the screen back on; sign-in itself, the health check and sign-out stay open.
 */
class EnsureScreenIsOn
{
    public function handle(Request $request, Closure $next): Response
    {
        $masterEntry = ($request->isMethod('GET') && $request->is('login') && $request->has('master')) || ($request->isMethod('POST') && $request->is('login'));

        if ($request->user()?->isMaster() || $masterEntry || $request->is('logout', 'up')) {
            return $next($request);
        }
        if (! SystemSetting::screenIsOff()) {
            return $this->sectionIsOff($request) ? response()->view('section-off', ['section' => ScreenSections::ALL[ScreenSections::keyFor($request)][0]], 503) : $next($request);
        }

        return response()->view('screen-off', [], 503);
    }

    /** A tab the master user has switched off answers everyone but the master with its own maintenance page (the dashboard, sign-in and sign-out stay open). */
    private function sectionIsOff(Request $request): bool
    {
        $key = ScreenSections::keyFor($request);

        return $key !== null && in_array($key, SystemSetting::sectionsOff(), true);
    }
}
