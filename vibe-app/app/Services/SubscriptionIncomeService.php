<?php

namespace App\Services;

use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Models\User;

/** What the subscriptions brought in (the amounts really paid, not the list price) and how it is divided; the dashboard of the master shows it. */
class SubscriptionIncomeService
{
    /** The share of the gross that pays for the system (hosting and the like). */
    public const SYSTEM_SHARE = 0.30;

    /** The share for allowance, maintenance and operating expense; what is left is the net income (50%). */
    public const OPERATING_SHARE = 0.20;

    /**
     * @return array{users: int, activation_requests: int, gross: float, system_subscription: float, operating: float, net: float}
     */
    public function summary(?int $year): array
    {
        $gross = round((float) SubscriptionPayment::withoutGlobalScopes()->when($year, fn ($query) => $query->whereYear('paid_at', $year))->sum('amount'), 2);
        $system = round($gross * self::SYSTEM_SHARE, 2);
        $operating = round($gross * self::OPERATING_SHARE, 2);

        return [
            'users' => User::withoutGlobalScopes()->whereNotIn('role', User::MASTER_ROLES)->count(),
            'activation_requests' => School::withoutGlobalScopes()->where('status', 'inactive')->count(),
            'gross' => $gross,
            'system_subscription' => $system,
            'operating' => $operating,
            // The net income is what is left, so the three parts always add up to the gross to the centavo.
            'net' => round($gross - $system - $operating, 2),
        ];
    }

    /** @return array<int, int> the years that have a payment, newest first (this year when there is none) */
    public function years(): array
    {
        $years = SubscriptionPayment::withoutGlobalScopes()->pluck('paid_at')->map(fn ($date) => (int) $date->format('Y'))->unique()->sortDesc()->values()->all();

        return $years ?: [(int) now()->format('Y')];
    }
}
