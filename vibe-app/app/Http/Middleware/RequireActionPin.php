<?php

namespace App\Http\Middleware;

use App\Rules\CurrentPin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edits and deletes of records need a reason and the user's own 6-digit PIN (the Reason + PIN window of partials/action-guard).
 * The routes that already check them in their controller are not in this list.
 */
class RequireActionPin
{
    public const ROUTES = [
        'aip.kras.update', 'aip.kras.destroy', 'aip.activities.destroy', 'aip.destroy',
        'budget.allocation.update', 'budget.allocation.destroy',
        'cash.fund.update', 'cash.fund.destroy', 'cash.report.register.checks.destroy',
        'chart-of-accounts.update', 'chart-of-accounts.destroy',
        'drive-files.destroy', 'financial-reports.opening.destroy',
        'planning.app-cse.update', 'planning.app-cse.items.update', 'planning.app-cse.items.destroy',
        'planning.app.destroy', 'planning.app.items.update', 'planning.app.items.destroy', 'planning.app.signatories',
        'planning.pow.update', 'planning.pow.destroy',
        'planning.sip.update', 'planning.sip.destroy', 'planning.sip.activities.update', 'planning.sip.activities.destroy',
        'planning.sob.update', 'planning.sob.destroy', 'planning.sob.groups.update', 'planning.sob.groups.destroy', 'planning.sob.items.update', 'planning.sob.items.destroy',
        'procurement.update', 'suppliers.update', 'units.destroy',
        'school-settings.employees.update', 'school-settings.employees.destroy', 'school-settings.papers.destroy', 'school-settings.users.update',
        'school-settings.printing.update', 'user-management.users.update', 'master-user.update', 'master-user.sub-masters.update', 'master-user.sub-masters.status',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->isMethodSafe() || ! $request->routeIs(...self::ROUTES)) {
            return $next($request);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'pin' => ['required', new CurrentPin]]);
        // The reason goes to the audit log; neither field reaches the controller as data.
        $request->attributes->set('action_reason', $data['reason']);
        $request->request->remove('pin');
        $request->request->remove('reason');

        return $next($request);
    }
}
