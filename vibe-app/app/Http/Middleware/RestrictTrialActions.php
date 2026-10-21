<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionPricing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The trial is for trying the system: it cannot print anything. The routes of every printed official document and cash report are listed here, in one place;
 * a trial user (never the master or a sub-master) gets the trial prompt instead. It runs before route-model binding, so the answer never depends on the record.
 */
class RestrictTrialActions
{
    public const PRINT_ROUTES = [
        'liquidation.print', 'accounting.dv.print', 'procurement.print', 'procurement.documents.print', 'procurement.delivery-reconciliation',
        'planning.sip.print', 'aip.print', 'planning.sob.print', 'planning.app.print', 'planning.app-cse.print', 'planning.pow.print',
        'cash.report.roci', 'cash.report.checks', 'cash.report.register', 'cash.report.bank', 'cash.report.history',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->seesAllSchools() || ! $user->activeSubscription()?->isTrial()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::PRINT_ROUTES, true)) {
            return $this->refuse($request, 'Printing is available on a paid subscription');
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
