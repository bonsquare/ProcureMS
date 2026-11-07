<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes every action a signed-in user does (anything that saves, changes or deletes, and signing in or out) to the audit log:
 * who, which action, the page, the answer of the system and the address. What the user typed is never stored, except the reason asked with the PIN.
 */
class RecordUserActions
{
    private const SKIPPED = ['*.preview', '*.promo', '*.fill', 'telegram.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $before = $request->user();
        $response = $next($request);

        if ($request->isMethodSafe() || $request->routeIs(...self::SKIPPED)) {
            return $response;
        }
        $user = $request->user() ?? $before;
        if (! $user) {
            return $response;
        }

        try {
            $name = (string) ($request->route()?->getName() ?: trim($request->path(), '/'));
            AuditLog::create([
                'user_id' => $user->id, 'school_id' => $user->school_id, 'organization_id' => $user->organization_id,
                'action' => str_replace(['.', '-', '/'], '_', $name),
                'metadata' => ['source' => 'request', 'method' => $request->method(), 'page' => '/'.ltrim($request->path(), '/'), 'result' => $response->getStatusCode() < 400 ? 'done' : 'refused ('.$response->getStatusCode().')', 'ip' => $request->ip()] + ($request->attributes->has('action_reason') ? ['reason' => $request->attributes->get('action_reason')] : []),
            ]);
        } catch (\Throwable) {
            // The record is never allowed to stop the user's action.
        }

        return $response;
    }
}
