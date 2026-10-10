<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a user's Google Drive connection needs to be renewed, adds the reconnect banner to every normal HTML
 * page. Official print pages and the Google Drive page itself are left alone.
 */
class ShowDriveReconnectBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $connection = $request->user()?->driveConnection;
        if (! $connection || $connection->isConnected() || ! $request->isMethod('GET') || $request->routeIs('google-drive') || $response->getStatusCode() !== 200) {
            return $response;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $content = $response->getContent();
        $end = $content === false ? false : strripos($content, '</body>');
        if ($end === false || str_contains($content, 'data-official-page')) {
            return $response;
        }

        $response->setContent(substr_replace($content, view('partials.drive-banner')->render(), $end, 0));

        return $response;
    }
}
