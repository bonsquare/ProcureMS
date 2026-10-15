<?php

namespace App\Http\Controllers;

use App\Models\Aip;
use App\Models\BudgetAllocation;
use App\Models\School;
use App\Services\AllotmentRegistryService;
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

        return $user->seesAllSchools() || $user->organization_id
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

        // Only the funded lines (the SOB's and the manual ones): a line with no allotment in the chosen period (and nothing obligated on it) is left out.
        $registry = $lines->map(fn (BudgetAllocation $line) => app(AllotmentRegistryService::class)->buildLine($line, $quarter))
            ->filter(fn (array $built) => $built['totals']['allotment'] > 0 || $built['totals']['obligation'] > 0)->values();
        $summary = $this->fundSummary($registry);

        if ($request->query('format') === 'csv') {
            return $this->csv($registry, $year);
        }

        return view('allotment-registry', [
            'year' => $year,
            'quarter' => $quarter,
            'registry' => $registry,
            'canManage' => $user->canManageBudget(),
            'summary' => $summary,
            'schools' => School::whereIn('id', $schoolIds)->orderBy('name')->get(),
            'selectedSchoolId' => $schoolId,
            'fund' => $fund,
            'funds' => BudgetAllocation::whereIn('school_id', $schoolIds)->distinct()->pluck('source_of_fund')->merge(collect(Aip::FUNDS)->flatten()->reject(fn ($fund) => $fund === 'Others'))->unique()->sort()->values(),
        ]);
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
