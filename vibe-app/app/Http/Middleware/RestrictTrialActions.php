<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionPricing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The trial is for trying the system: it cannot print anything, record a payment, upload general files to Google Drive or make and download backups. The routes of every printed official document and cash report are listed here, in one place;
 * a trial user (never the master or a sub-master) gets the trial prompt instead. It runs before route-model binding, so the answer never depends on the record.
 */
class RestrictTrialActions
{
    public const PRINT_ROUTES = [
        'liquidation.print', 'accounting.dv.print', 'procurement.print', 'procurement.documents.print', 'procurement.delivery-reconciliation',
        'planning.sip.print', 'aip.print', 'planning.sob.print', 'planning.app.print', 'planning.app-cse.print', 'planning.pow.print',
        'cash.report.roci', 'cash.report.checks', 'cash.report.register', 'cash.report.bank', 'cash.report.history', 'dashboard.audit-logs.export',
    ];

    /** Pages that can be read but also print or export themselves: blocked only when asked to print (`?print=1`) or to export (`?format=csv`). */
    public const PRINT_WHEN_ASKED = ['budget.allocation.report', 'allotment-registry', 'budget.allocation.show'];

    /** Other things a trial cannot do: route name => what the prompt says is available on the paid subscription. */
    public const ACTION_ROUTES = [
        'cash.pay' => 'Recording a payment', 'cash.payment.update' => 'Recording a payment', 'cash.cancel' => 'Cancelling a check',
        'drive-files.store' => 'Uploading files to Google Drive', 'backup.run' => 'Making a backup', 'backup.download' => 'Downloading a backup',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->seesAllSchools() || ! $user->activeSubscription()?->isLimited()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::PRINT_ROUTES, true)) {
            return $this->refuse($request, 'Printing is available on a paid subscription');
        }

        if (in_array($request->route()?->getName(), self::PRINT_WHEN_ASKED, true) && ($request->boolean('print') || $request->query('format') === 'csv')) {
            return $this->refuse($request, 'Printing is available on a paid subscription');
        }

        if ($action = self::ACTION_ROUTES[$request->route()?->getName()] ?? null) {
            return $this->refuse($request, $action.' is available on a paid subscription');
        }

        return $next($request);
    }

    private function refuse(Request $request, string $title): Response
    {
        $message = $title.' (PHP '.number_format(SubscriptionPricing::LIST_PRICE, 2).' a year). You are on the trial: ask the administrator to activate your school.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 402)
            : response()->view('trial-prompt', ['title' => $title, 'price' => SubscriptionPricing::LIST_PRICE], 402);
    }
}
