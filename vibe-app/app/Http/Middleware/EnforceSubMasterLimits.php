<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The three delete-like switches of a Sub-master. It refuses, for a Sub-master only: every DELETE request
 * (method spoofing included) unless the 'delete' switch is on, deactivating a user unless 'deactivate_user' is on,
 * and closing a budget unless 'close_budget' is on. A user's own Google Drive actions are never refused.
 * It runs before route-model binding, so a refusal never depends on the record existing.
 */
class EnforceSubMasterLimits
{
    public const MESSAGE = "Your account's access does not allow this action.";

    private const PERSONAL_ROUTES = ['google-drive.disconnect', 'drive-files.destroy'];

    private const SWITCH_BY_ROUTE = [
        'school-management.users.deactivate' => 'deactivate_user',
        'user-management.users.deactivate' => 'deactivate_user',
        'budget.allocation.close' => 'close_budget',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isSubMaster()) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        $required = self::SWITCH_BY_ROUTE[$route] ?? $this->conditionalSwitch($request, $route);
        if ($required === null && $request->isMethod('DELETE') && ! in_array($route, self::PERSONAL_ROUTES, true)) {
            $required = 'delete';
        }

        if ($required !== null && ! $user->hasAccess($required)) {
            AuditLog::create([
                'user_id' => $user->id, 'action' => 'sub_master_action_refused', 'auditable_type' => User::class, 'auditable_id' => $user->id,
                'metadata' => ['route' => $route, 'method' => $request->method(), 'needs' => $required],
            ]);

            abort(403, self::MESSAGE);
        }

        return $next($request);
    }

    /** Actions that deactivate or close something through a normal form field instead of a dedicated route. */
    private function conditionalSwitch(Request $request, ?string $route): ?string
    {
        return match (true) {
            $route === 'school-management.handover.end' => 'deactivate_user',
            $route === 'school-management.status' && ! filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN) => 'deactivate_user',
            in_array($route, ['school-settings.users.update', 'school-settings.school'], true) && $request->input('status') === 'inactive' => 'deactivate_user',
            $route === 'planning.fiscal-year.status' && $request->input('status') === 'closed' => 'close_budget',
            default => null,
        };
    }
}
