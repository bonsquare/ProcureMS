<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A user set inactive, or whose school was set inactive, is signed out on the next request. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === 'master_user' || $request->routeIs('logout')) {
            return $next($request);
        }

        $schoolInactive = $user->school_id && School::withoutGlobalScopes()->whereKey($user->school_id)->value('status') === 'inactive';
        if ($user->status === 'inactive' || $schoolInactive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active. Contact the system administrator.']);
        }

        return $next($request);
    }
}
