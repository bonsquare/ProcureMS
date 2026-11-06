<?php

namespace App\Services;

use App\Models\LiquidationReport;
use App\Models\School;
use Carbon\Carbon;

/**
 * The Cash Disbursement summaries of one school and period: cash position, tax withheld and remitted, advances, and paid DVs by payee.
 * Read-only; the figures come from the journals and the DVs (nothing is posted or changed here).
 */
class CashSummaryService
{
    private const CASH = '1010404000';

    private const ADVANCES = '1990101000';

    public function __construct(private readonly CashReportService $cash, private readonly GeneralLedgerService $books) {}

    /** @return array<string, mixed> */
    public function build(School $school, Carbon $from, Carbon $to): array
    {
        $dvs = LiquidationReport::query()->with('journalLines')->where('school_id', $school->id)->where('status', 'approved')->whereNotNull('dv_number')->get();
        $cashDvs = $dvs->reject->dv_non_cash;
        $net = fn (LiquidationReport $dv) => round((float) $dv->amount - $this->cash->deductionsOf($dv), 2);

        $trial = collect($this->books->trialBalance([$school->id], null, $from, $to)['rows'])->keyBy('account_code');
        $cashRow = $trial->get(self::CASH);
        $advanceRow = $trial->get(self::ADVANCES);
        $position = [
            'opening' => round((float) ($cashRow['opening_debit'] ?? 0) - (float) ($cashRow['opening_credit'] ?? 0), 2),
            'received' => (float) ($cashRow['period_debit'] ?? 0),
            'paid' => (float) ($cashRow['period_credit'] ?? 0),
            'ending' => round((float) ($cashRow['ending_debit'] ?? 0) - (float) ($cashRow['ending_credit'] ?? 0), 2),
        ];

        // The tax a DV withholds is paid by a remittance DV that names the DVs it pays; what is not named is still to be remitted.
        $remitted = [];
        foreach ($cashDvs->where('dv_date', '<=', $to->copy()->endOfDay()) as $remittance) {
            foreach ($remittance->remittance['sources'] ?? [] as $sourceId => $amount) {
                $remitted[(int) $sourceId] = ($remitted[(int) $sourceId] ?? 0) + (float) $amount;
            }
        }
        $tax = $cashDvs->filter(fn (LiquidationReport $dv) => $dv->dv_date && $dv->dv_date->lte($to) && $this->cash->deductionsOf($dv) > 0)->sortBy('dv_date')->map(fn (LiquidationReport $dv) => [
            'dv' => LiquidationReport::dvLabel($dv->dv_number), 'date' => $dv->dv_date, 'payee' => $dv->payee,
            'withheld' => $this->cash->deductionsOf($dv), 'remitted' => round($remitted[$dv->id] ?? 0, 2),
            'unremitted' => round($this->cash->deductionsOf($dv) - ($remitted[$dv->id] ?? 0), 2),
        ])->values();

        $advanceDvs = $dvs->where('dv_non_cash', true)->filter(fn (LiquidationReport $dv) => $dv->dv_date && $dv->dv_date->lte($to))->sortBy('dv_date');
        $received = round((float) $advanceDvs->sum(fn (LiquidationReport $dv) => $dv->journalLines->where('account_code', self::ADVANCES)->sum('credit')), 2);
        $outstanding = round((float) ($advanceRow['ending_credit'] ?? 0) - (float) ($advanceRow['ending_debit'] ?? 0), 2);
        $advances = [
            'rows' => $advanceDvs->map(fn (LiquidationReport $dv) => [
                'dv' => LiquidationReport::dvLabel($dv->dv_number), 'date' => $dv->dv_date, 'particulars' => $dv->dv_particulars ?: $dv->purpose,
                'received' => round((float) $dv->journalLines->where('account_code', self::ADVANCES)->sum('credit'), 2),
                'liquidated' => round((float) $dv->journalLines->where('account_code', self::ADVANCES)->sum('debit'), 2),
            ])->values(),
            'received' => $received, 'outstanding' => $outstanding, 'liquidated' => round($received - $outstanding, 2),
        ];

        $paid = $cashDvs->filter(fn (LiquidationReport $dv) => $dv->paid_at && $dv->paid_at->between($from->copy()->startOfDay(), $to->copy()->endOfDay()));
        $payees = $paid->groupBy(fn (LiquidationReport $dv) => trim((string) $dv->payee))->map(fn ($group, $payee) => [
            'payee' => $payee, 'count' => $group->count(), 'gross' => round((float) $group->sum('amount'), 2),
            'tax' => round((float) $group->sum(fn (LiquidationReport $dv) => $this->cash->deductionsOf($dv)), 2), 'net' => round((float) $group->sum($net), 2),
        ])->sortByDesc('net')->values();

        return [
            'position' => $position, 'tax' => $tax, 'advances' => $advances, 'payees' => $payees,
            'payeeTotals' => ['count' => $payees->sum('count'), 'gross' => round((float) $payees->sum('gross'), 2), 'tax' => round((float) $payees->sum('tax'), 2), 'net' => round((float) $payees->sum('net'), 2)],
        ];
    }
}
