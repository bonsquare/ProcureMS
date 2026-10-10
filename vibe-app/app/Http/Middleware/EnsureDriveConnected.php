<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks file uploads until the signed-in user has a working Google Drive connection. */
class EnsureDriveConnected
{
    public const MESSAGE = 'Connect your Google Drive before uploading files.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->driveIsConnected()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 409);
        }

        return redirect()->route('google-drive')->with('error', self::MESSAGE);
    }
}
