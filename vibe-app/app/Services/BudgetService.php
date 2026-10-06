<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    /**
     * Allocation vs. obligations for one school + fund + fiscal year.
     * Obligations are PRs plus ORS entries that have no PR (PR-linked ORS are already counted via the PR).
     */
    public function position(int $schoolId, ?string $fund, ?int $year = null, ?int $ignorePrId = null, ?int $ignoreOrsId = null): array
    {
        $year ??= now()->year;
        $fund = trim((string) $fund);

        $allocations = BudgetAllocation::where('school_id', $schoolId)->where('fiscal_year', $year)->where('source_of_fund', $fund);
        $allocated = (float) $allocations->sum('amount');

        $obligated = (float) ProcurementRequest::where('school_id', $schoolId)->where('source_of_fund', $fund)->whereYear('created_at', $year)
            ->when($ignorePrId, fn ($q) => $q->where('id', '!=', $ignorePrId))->sum('amount')
            + (float) LiquidationReport::where('school_id', $schoolId)->where('source_of_fund', $fund)->whereNull('procurement_request_id')->whereYear('created_at', $year)
            ->when($ignoreOrsId, fn ($q) => $q->where('id', '!=', $ignoreOrsId))->sum('amount');

        return [
            'has_allocation' => $allocations->exists(),
            'allocated' => $allocated,
            'obligated' => $obligated,
            'balance' => $allocated - $obligated,
        ];
    }

    /** Block an obligation that would exceed the allotment (only when an allocation exists). */
    public function assertAvailable(string $field, int $schoolId, ?string $fund, float $amount, ?int $year = null, ?int $ignorePrId = null, ?int $ignoreOrsId = null): void
    {
        $position = $this->position($schoolId, $fund, $year, $ignorePrId, $ignoreOrsId);

        if ($position['has_allocation'] && $amount > $position['balance'] + 0.001) {
            throw ValidationException::withMessages([
                $field => sprintf('Insufficient budget: ₱%s requested but only ₱%s remains in %s.', number_format($amount, 2), number_format(max($position['balance'], 0), 2), $fund),
            ]);
        }
    }

    /**
     * Per-item quarterly matrix for the Budget Allocation register.
     * Obligations = PRs (and ORS without a PR) linked to the item; liquidations = approved ORS under them.
     *
     * @param  \Illuminate\Support\Collection<int, BudgetAllocation>  $allocations
     */
    public function matrix($allocations)
    {
        $ids = $allocations->pluck('id');
        $prs = ProcurementRequest::with('liquidationReports')->whereIn('budget_allocation_id', $ids)->get()->groupBy('budget_allocation_id');
        $direct = LiquidationReport::whereNull('procurement_request_id')->whereIn('budget_allocation_id', $ids)->get()->groupBy('budget_allocation_id');

        return $allocations->map(function (BudgetAllocation $a) use ($prs, $direct) {
            $quarters = [];
            foreach ([1, 2, 3, 4] as $q) {
                $quarters[$q] = ['allocated' => $a->quarterAmount($q), 'obligated' => 0.0, 'liquidated' => 0.0];
            }

            foreach ($prs->get($a->id, collect()) as $pr) {
                $quarters[$pr->created_at->quarter]['obligated'] += (float) $pr->amount;
                foreach ($pr->liquidationReports->where('status', 'approved') as $report) {
                    $quarters[$report->created_at->quarter]['liquidated'] += (float) $report->amount;
                }
            }
            foreach ($direct->get($a->id, collect()) as $ors) {
                $quarters[$ors->created_at->quarter]['obligated'] += (float) $ors->amount;
                if ($ors->status === 'approved') {
                    $quarters[$ors->created_at->quarter]['liquidated'] += (float) $ors->amount;
                }
            }

            foreach ($quarters as $q => $row) {
                $quarters[$q]['balance'] = $row['allocated'] - $row['obligated'];
            }

            $allocated = (float) $a->amount;
            $obligated = array_sum(array_column($quarters, 'obligated'));
            $liquidated = array_sum(array_column($quarters, 'liquidated'));

            return [
                'item' => $a,
                'quarters' => $quarters,
                'allocated' => $allocated,
                'obligated' => $obligated,
                'liquidated' => $liquidated,
                'balance' => $allocated - $obligated,
                'unliquidated' => $obligated - $liquidated,
                'status' => $this->status($a, $allocated, $obligated, $liquidated),
                'warnings' => array_values(array_filter([
                    $obligated > $allocated + 0.001 ? 'Insufficient Budget Balance' : null,
                    $liquidated > $obligated + 0.001 ? 'Liquidated amount cannot exceed obligated amount' : null,
                ])),
            ];
        });
    }

    public function status(BudgetAllocation $a, float $allocated, float $obligated, float $liquidated): string
    {
        return match (true) {
            $a->closed_at !== null => 'Closed',
            $obligated > $allocated + 0.001 => 'Over Budget',
            $obligated <= 0 => 'Not Yet Used',
            $allocated > 0 && $liquidated >= $allocated - 0.001 => 'Fully Liquidated',
            $liquidated > 0 => 'Partially Liquidated',
            $obligated >= $allocated - 0.001 => 'Fully Obligated',
            default => 'Partially Obligated',
        };
    }

    /** Block an obligation against a specific budget item that is closed or has too little balance. */
    public function assertItemAvailable(string $field, ?int $budgetAllocationId, float $amount, ?int $ignorePrId = null, ?int $ignoreOrsId = null): void
    {
        if (!$budgetAllocationId) {
            return;
        }

        $item = BudgetAllocation::findOrFail($budgetAllocationId);
        if ($item->closed_at) {
            throw ValidationException::withMessages([$field => "Budget item {$item->particulars} is closed and cannot take new obligations."]);
        }

        $obligated = (float) ProcurementRequest::where('budget_allocation_id', $item->id)
            ->when($ignorePrId, fn ($q) => $q->where('id', '!=', $ignorePrId))->sum('amount')
            + (float) LiquidationReport::where('budget_allocation_id', $item->id)->whereNull('procurement_request_id')
            ->when($ignoreOrsId, fn ($q) => $q->where('id', '!=', $ignoreOrsId))->sum('amount');
        $balance = (float) $item->amount - $obligated;

        if ($amount > $balance + 0.001) {
            throw ValidationException::withMessages([
                $field => sprintf('Insufficient Budget Balance: ₱%s requested but only ₱%s remains in %s.', number_format($amount, 2), number_format(max($balance, 0), 2), $item->particulars),
            ]);
        }
    }
}
