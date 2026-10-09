<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardKpiService
{
    public function __construct(private BudgetService $budget) {}

    /**
     * KPI figures for the dashboard, limited to the given schools and fiscal year.
     *
     * @param  Collection<int, int>  $schoolIds
     * @return array<string, mixed>
     */
    public function forSchools(Collection $schoolIds, ?int $year = null): array
    {
        $year ??= now()->year;
        $schoolIds = $schoolIds->values();

        $requests = ProcurementRequest::withoutGlobalScopes()->whereIn('school_id', $schoolIds)->whereYear('created_at', $year)->get(['id', 'school_id', 'request_number', 'title', 'status', 'amount', 'created_at']);
        $reports = LiquidationReport::withoutGlobalScopes()->whereIn('school_id', $schoolIds)->whereYear('created_at', $year)->get(['id', 'school_id', 'status', 'amount']);
        $allocations = BudgetAllocation::withoutGlobalScopes()->whereIn('school_id', $schoolIds)->where('fiscal_year', $year)->get();
        $matrix = $allocations->isEmpty() ? collect() : $this->budget->matrix($allocations);
        $suppliers = Supplier::withoutGlobalScopes()->whereIn('school_id', $schoolIds)->get(['id', 'status', 'business_permit_expiry', 'philgeps_expiry']);

        $byStatus = $requests->groupBy('status')->map->count();
        $total = $requests->count();
        $pending = $requests->whereIn('status', ['submitted', 'pending_approval'])->count();
        $completed = $requests->where('status', 'completed')->count();
        $closedOut = $requests->whereIn('status', ['returned', 'rejected'])->count();

        $allocated = (float) $matrix->sum('allocated');
        $obligated = (float) $matrix->sum('obligated');
        $liquidated = (float) $matrix->sum('liquidated');

        return [
            'year' => $year,
            'procurement' => [
                'total' => $total,
                'pending' => $pending,
                'in_progress' => $requests->whereIn('status', ['approved', 'for_canvass'])->count(),
                'completed' => $completed,
                'attention' => $closedOut,
                'completion_rate' => $total ? (int) round($completed / $total * 100) : 0,
                'value' => (float) $requests->sum('amount'),
                'by_status' => $byStatus->all(),
                'monthly' => $this->monthly($requests),
            ],
            'budget' => [
                'allocated' => $allocated,
                'obligated' => $obligated,
                'liquidated' => $liquidated,
                'balance' => $allocated - $obligated,
                'utilization' => $allocated > 0 ? (int) round($obligated / $allocated * 100) : 0,
                'liquidation_rate' => $obligated > 0 ? (int) round($liquidated / $obligated * 100) : 0,
                'near_limit' => $matrix->whereIn('status', ['Near Limit', 'Fully Obligated'])->count(),
                'quarters' => collect([1, 2, 3, 4])->mapWithKeys(fn (int $quarter) => [$quarter => [
                    'allocated' => (float) $matrix->sum(fn (array $row) => $row['quarters'][$quarter]['allocated']),
                    'obligated' => (float) $matrix->sum(fn (array $row) => $row['quarters'][$quarter]['obligated']),
                ]])->all(),
                'lines' => $allocations->count(),
            ],
            'liquidation' => [
                'total' => $reports->count(),
                'pending' => $reports->where('status', 'for_review')->count(),
                'approved' => $reports->where('status', 'approved')->count(),
                'pending_amount' => (float) $reports->where('status', 'for_review')->sum('amount'),
            ],
            'suppliers' => [
                'active' => $suppliers->where('status', 'active')->count(),
                'expiring' => $suppliers->filter(fn ($supplier) => $this->expiresWithin($supplier->business_permit_expiry) || $this->expiresWithin($supplier->philgeps_expiry))->count(),
            ],
            'recent' => $requests->sortByDesc('created_at')->take(5)->map(fn ($request) => [
                'id' => $request->id,
                'number' => $request->request_number,
                'title' => $request->title,
                'status' => $request->status,
                'amount' => (float) $request->amount,
            ])->values()->all(),
            'schools' => $this->perSchool($schoolIds, $requests, $matrix),
        ];
    }

    private function expiresWithin($date, int $days = 30): bool
    {
        return $date !== null && Carbon::parse($date)->lte(now()->addDays($days));
    }

    /** Requests per month for the six months up to now. */
    private function monthly(Collection $requests): array
    {
        return collect(range(5, 0))->map(function (int $back) use ($requests) {
            $month = now()->startOfMonth()->subMonths($back);
            $inMonth = $requests->filter(fn ($request) => $request->created_at->isSameMonth($month));

            return ['label' => $month->format('M'), 'count' => $inMonth->count(), 'value' => (float) $inMonth->sum('amount')];
        })->all();
    }

    /** One row per school, for the administrator's comparison table. */
    private function perSchool(Collection $schoolIds, Collection $requests, Collection $matrix): array
    {
        $names = School::withoutGlobalScopes()->whereIn('id', $schoolIds)->pluck('name', 'id');

        return $schoolIds->map(function ($schoolId) use ($names, $requests, $matrix) {
            $schoolRequests = $requests->where('school_id', $schoolId);
            $lines = $matrix->filter(fn (array $row) => (int) $row['item']->school_id === (int) $schoolId);
            $allocated = (float) $lines->sum('allocated');
            $obligated = (float) $lines->sum('obligated');

            return [
                'name' => $names[$schoolId] ?? 'School #'.$schoolId,
                'requests' => $schoolRequests->count(),
                'pending' => $schoolRequests->whereIn('status', ['submitted', 'pending_approval'])->count(),
                'completed' => $schoolRequests->where('status', 'completed')->count(),
                'allocated' => $allocated,
                'obligated' => $obligated,
                'utilization' => $allocated > 0 ? (int) round($obligated / $allocated * 100) : null,
            ];
        })->sortByDesc('requests')->values()->all();
    }
}
