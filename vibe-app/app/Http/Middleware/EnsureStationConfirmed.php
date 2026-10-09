<?php

namespace App\Http\Middleware;

use App\Models\StationTransferRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === 'master_user' || $request->routeIs('station.confirm', 'station.confirm.store', 'logout')) {
            return $next($request);
        }

        $unconfirmed = StationTransferRequest::where('user_id', $user->id)->where('status', 'approved')->whereNull('confirmed_at')->exists();

        return $unconfirmed ? redirect()->route('station.confirm') : $next($request);
    }
}
