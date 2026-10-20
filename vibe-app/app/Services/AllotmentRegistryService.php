<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use Illuminate\Support\Carbon;

/**
 * The Registry of Allotments, Obligations and Disbursements for one Budget line: the allotment, each ORS (obligation) and each paid DV
 * (disbursement), with running balances. The Allotment Registry and the Budget Allocation page read their obligations from here, so both agree.
 */
class AllotmentRegistryService
{
    private function quarterOf(?\DateTimeInterface $date): int
    {
        return $date ? Carbon::instance($date)->quarter : 0;
    }

    public function buildLine(BudgetAllocation $line, int $quarter): array
    {
        $allotment = $quarter ? $line->quarterAmount($quarter) : (float) $line->amount;

        $entries = collect([[
            'date' => $line->start_date ?? $line->created_at, 'type' => 'Allotment', 'ref' => $line->budget_ref_no, 'payee' => '',
            'particulars' => $line->program ?: 'Allotment', 'allotment' => $allotment, 'obligation' => 0.0, 'disbursement' => 0.0, 'quarter' => $quarter,
        ]]);

        $prIds = ProcurementRequest::where('budget_allocation_id', $line->id)->pluck('id');
        // An ORS charged to several budget lines is on each of them with the part of its amount that line carries.
        $reports = LiquidationReport::with('lines')
            ->where(fn ($q) => $q->where(fn ($own) => $own->whereDoesntHave('lines')->where(fn ($same) => $same->where('budget_allocation_id', $line->id)->orWhereIn('procurement_request_id', $prIds)))
                ->orWhereHas('lines', fn ($part) => $part->where('budget_allocation_id', $line->id)))
            ->whereNotIn('status', ['returned', 'rejected'])->get();

        foreach ($reports as $ors) {
            $part = $ors->lines->isEmpty() ? (float) $ors->amount : (float) $ors->lines->where('budget_allocation_id', $line->id)->sum('amount');
            $obligatedOn = $ors->submitted_at ?? $ors->created_at;
            $entries->push([
                'date' => $obligatedOn, 'type' => 'Obligation', 'ref' => $ors->ors_number, 'payee' => $ors->payee, 'particulars' => $ors->purpose,
                'allotment' => 0.0, 'obligation' => $part, 'disbursement' => 0.0, 'quarter' => $this->quarterOf($obligatedOn),
            ]);
            if ($ors->paid_at) {
                $entries->push([
                    'date' => $ors->paid_at, 'type' => 'Disbursement', 'ref' => trim(($ors->dv_number ? LiquidationReport::dvLabel($ors->dv_number) : '').' '.$ors->payment_reference), 'payee' => $ors->payee,
                    'particulars' => $ors->dv_particulars ?: $ors->purpose, 'allotment' => 0.0, 'obligation' => 0.0, 'disbursement' => $part, 'quarter' => $this->quarterOf($ors->paid_at),
                ]);
            }
        }

        $entries = $entries->filter(fn ($e) => $e['type'] === 'Allotment' || ! $quarter || $e['quarter'] === $quarter)
            ->sortBy(fn ($e) => [$e['type'] === 'Allotment' ? 0 : 1, $e['date']?->copy()->startOfDay()->timestamp ?? 0, $e['type'] === 'Disbursement' ? 1 : 0, $e['date']?->timestamp ?? 0])->values();

        $allotted = $obligated = $disbursed = 0.0;
        $entries = $entries->map(function ($e) use (&$allotted, &$obligated, &$disbursed) {
            $allotted += $e['allotment'];
            $obligated += $e['obligation'];
            $disbursed += $e['disbursement'];

            return $e + ['not_obligated' => $allotted - $obligated, 'unpaid' => $obligated - $disbursed];
        });

        return [
            'line' => $line, 'entries' => $entries,
            'totals' => ['allotment' => $allotted, 'obligation' => $obligated, 'disbursement' => $disbursed, 'not_obligated' => $allotted - $obligated, 'unpaid' => $obligated - $disbursed],
        ];
    }
}
