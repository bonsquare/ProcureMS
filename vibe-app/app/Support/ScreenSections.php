<?php

namespace App\Support;

use Illuminate\Http\Request;

/** The tabs (work areas) of the system the master user can switch off one by one; the dashboard always stays open. Each is found by the address it lives under. */
class ScreenSections
{
    /** @var array<string, array{0: string, 1: array<int, string>}> key => [label, address patterns] */
    public const ALL = [
        'budget' => ['Budget', ['budget', 'budget/*']],
        'accounting' => ['Accounting', ['accounting', 'accounting/*']],
        'chart_of_accounts' => ['Chart of Accounts', ['accounting/chart-of-accounts*']],
        'allotment_registry' => ['Allotment Registry', ['allotment-registry*']],
        'cash' => ['Cash', ['cash', 'cash/*']],
        'procurement' => ['Procurement', ['procurement', 'procurement/*']],
        'liquidation' => ['Liquidation', ['liquidation', 'liquidation/*']],
        'planning' => ['Planning', ['planning', 'planning/*', 'aip/*']],
        'google_drive' => ['Google Drive', ['google-drive*', 'drive-files*']],
        'reports' => ['Reports', ['reports*']],
        'user_management' => ['User Management', ['user-management*', 'audit-logs*']],
        'subscriptions' => ['Subscriptions', ['subscriptions*']],
        'school_management' => ['School Management', ['school-management*']],
        'school_settings' => ['School Settings', ['school-settings*']],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** The tab a request belongs to (the most specific one wins: Chart of Accounts before Accounting), or null for the dashboard and everything else. */
    public static function keyFor(Request $request): ?string
    {
        foreach (['chart_of_accounts', ...array_diff(self::keys(), ['chart_of_accounts'])] as $key) {
            if ($request->is(...self::ALL[$key][1])) {
                return $key;
            }
        }

        return null;
    }
}
