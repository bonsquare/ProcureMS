<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use Illuminate\Http\Request;

/**
 * Registry of Allotments, Obligations and Disbursements (RAOD): per fund and account code, the
 * allotment, each ORS (obligation) and each paid DV (disbursement), with running balances.
 */
class AllotmentRegistryController extends Controller
{
    private function schoolIds()
    {
        $user = request()->user();

        return $user->role === 'master_user' || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $quarter = (int) $request->query('quarter', 0);
        $schoolId = $request->query('school_id');
        $fund = $request->query('fund');
        $schoolIds = $this->schoolIds();
        $user = $request->user();

        $lines = BudgetAllocation::with('school')->where('fiscal_year', $year)->whereIn('school_id', $schoolIds)
            ->when($schoolId && $schoolIds->contains((int) $schoolId), fn ($q) => $q->where('school_id', $schoolId))
            ->when($fund, fn ($q) => $q->where('source_of_fund', $fund))
            ->when($user->role === 'office_user' && $user->office, fn ($q) => $q->where('office', $user->office))
            ->orderBy('source_of_fund')->orderBy('uacs_code')->get();

        $registry = $lines->map(fn (BudgetAllocation $line) => $this->buildLine($line, $quarter));
        $summary = $this->fundSummary($registry);

        if ($request->query('format') === 'csv') {
            return $this->csv($registry, $year);
        }

        return view('allotment-registry', [
            'year' => $year,
            'quarter' => $quarter,
            'registry' => $registry,
            'summary' => $summary,
            'schools' => School::whereIn('id', $schoolIds)->orderBy('name')->get(),
            'selectedSchoolId' => $schoolId,
            'fund' => $fund,
            'funds' => BudgetAllocation::whereIn('school_id', $schoolIds)->distinct()->pluck('source_of_fund')->merge(collect(\App\Models\Aip::FUNDS)->flatten()->reject(fn ($fund) => $fund === 'Others'))->unique()->sort()->values(),
        ]);
    }

    private function quarterOf(?\DateTimeInterface $date): int
    {
        return $date ? \Illuminate\Support\Carbon::instance($date)->quarter : 0;
    }

    private function buildLine(BudgetAllocation $line, int $quarter): array
    {
        $allotment = $quarter ? $line->quarterAmount($quarter) : (float) $line->amount;

        $entries = collect([[
            'date' => $line->start_date ?? $line->created_at, 'type' => 'Allotment', 'ref' => $line->budget_ref_no, 'payee' => '',
            'particulars' => $line->program ?: 'Allotment', 'allotment' => $allotment, 'obligation' => 0.0, 'disbursement' => 0.0, 'quarter' => $quarter,
        ]]);

        $prIds = ProcurementRequest::where('budget_allocation_id', $line->id)->pluck('id');
        $reports = LiquidationReport::where(fn ($q) => $q->where('budget_allocation_id', $line->id)->orWhereIn('procurement_request_id', $prIds))
            ->whereNotIn('status', ['returned', 'rejected'])->get();

        foreach ($reports as $ors) {
            $obligatedOn = $ors->submitted_at ?? $ors->created_at;
            $entries->push([
                'date' => $obligatedOn, 'type' => 'Obligation', 'ref' => $ors->ors_number, 'payee' => $ors->payee, 'particulars' => $ors->purpose,
                'allotment' => 0.0, 'obligation' => (float) $ors->amount, 'disbursement' => 0.0, 'quarter' => $this->quarterOf($obligatedOn),
            ]);
            if ($ors->paid_at) {
                $entries->push([
                    'date' => $ors->paid_at, 'type' => 'Disbursement', 'ref' => trim(($ors->dv_number ? 'DV ' . $ors->dv_number : '') . ' ' . $ors->payment_reference), 'payee' => $ors->payee,
                    'particulars' => $ors->dv_particulars ?: $ors->purpose, 'allotment' => 0.0, 'obligation' => 0.0, 'disbursement' => (float) $ors->amount, 'quarter' => $this->quarterOf($ors->paid_at),
                ]);
            }
        }

        $entries = $entries->filter(fn ($e) => $e['type'] === 'Allotment' || !$quarter || $e['quarter'] === $quarter)
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

    /** Allotments and obligations per source of fund and quarter (the AIP's summary table, with actuals). */
    private function fundSummary($registry)
    {
        return $registry->groupBy(fn ($r) => $r['line']->source_of_fund)->map(fn ($group, $fund) => [
            'allotment' => collect([1, 2, 3, 4])->map(fn ($q) => $group->sum(fn ($r) => $r['line']->quarterAmount($q)))->all(),
            'allotment_total' => $group->sum(fn ($r) => (float) $r['line']->amount),
            'obligation' => $group->sum(fn ($r) => $r['totals']['obligation']),
            'disbursement' => $group->sum(fn ($r) => $r['totals']['disbursement']),
        ]);
    }

    private function csv($registry, int $year)
    {
        return response()->streamDownload(function () use ($registry) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fund Source', 'UACS Object Code', 'Expense Item', 'Date', 'Entry', 'ORS/DV/Check No.', 'Payee', 'Particulars', 'Allotment', 'Obligation', 'Disbursement', 'Allotment Not Yet Obligated', 'Unpaid Obligation']);
            foreach ($registry as $r) {
                foreach ($r['entries'] as $e) {
                    fputcsv($out, [$r['line']->source_of_fund, $r['line']->uacs_code, $r['line']->particulars, $e['date']?->format('Y-m-d'), $e['type'], $e['ref'], $e['payee'], $e['particulars'], $e['allotment'], $e['obligation'], $e['disbursement'], $e['not_obligated'], $e['unpaid']]);
                }
            }
            fclose($out);
        }, "allotment-registry-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
