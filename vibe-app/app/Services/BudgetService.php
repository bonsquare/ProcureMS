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
}
