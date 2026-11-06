<?php

namespace App\Http\Controllers;

use App\Models\AipActivity;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Services\AllotmentRegistryService;
use App\Services\CashReportService;
use Illuminate\Http\Request;

/**
 * The reports of the Reports & Analytics page that are generated here, from the records, as a table with totals and a CSV file
 * (the planning, allotment, accounting and cash reports used to open the pages of those modules instead).
 */
class GeneratedReportController extends Controller
{
    public const REPORTS = [
        'aip' => 'Annual Implementation Plan (AIP) Report',
        'allotment-registry' => 'Allotment Registry Report',
        'chart-of-accounts' => 'Chart of Accounts Report',
        'accounting' => 'Accounting: ORS and DV Report',
        'cash' => 'Cash Disbursement Report',
    ];

    private function schoolIds()
    {
        $user = request()->user();

        return $user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(self::REPORTS[$type]), 404);
        $user = $request->user();
        abort_unless(match ($type) {
            'aip' => $user->hasPermission('planning.view'),
            'allotment-registry' => $user->hasPermission('budget.view'),
            'chart-of-accounts' => $user->hasPermission('accounting.view') || $user->hasPermission('accounting.manage'),
            'accounting' => $user->hasPermission('accounting.view'),
            'cash' => $user->hasPermission('cash.pay'),
        }, 403);

        $year = (int) $request->query('year', now()->year);
        $schoolIds = $this->schoolIds();
        $schoolId = $request->query('school_id');
        $scoped = $schoolId && $schoolIds->contains((int) $schoolId) ? collect([(int) $schoolId]) : $schoolIds;
        [$columns, $lines, $money] = match ($type) {
            'aip' => $this->aip($year, $scoped),
            'allotment-registry' => $this->allotmentRegistry($year, $scoped),
            'chart-of-accounts' => $this->chartOfAccounts(),
            'accounting' => $this->accounting($year, $scoped),
            'cash' => $this->cash($year, $scoped),
        };

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($columns, $lines) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $columns);
                foreach ($lines as $line) {
                    fputcsv($out, $line);
                }
                fclose($out);
            }, "report-{$type}-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('generated-report', [
            'title' => self::REPORTS[$type], 'type' => $type, 'year' => $year, 'columns' => $columns, 'lines' => $lines, 'money' => $money,
            'schools' => School::whereIn('id', $schoolIds)->orderBy('name')->get(), 'selectedSchoolId' => $schoolId,
            'schoolName' => $schoolId ? School::find($schoolId)?->name : null, 'showYear' => $type !== 'chart-of-accounts',
        ]);
    }

    /**
     * The rows with a TOTAL row for the money columns.
     *
     * @param  array<int, array<int, mixed>>  $lines
     * @param  array<int, int>  $money
     * @return array<int, array<int, mixed>>
     */
    private function withTotal(array $lines, array $money, int $columns): array
    {
        if ($lines === []) {
            return $lines;
        }
        $total = array_fill(0, $columns, '');
        $total[0] = 'TOTAL';
        foreach ($money as $index) {
            $total[$index] = round(array_sum(array_column($lines, $index)), 2);
        }

        return [...$lines, $total];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, int>} */
    private function aip(int $year, $schoolIds): array
    {
        $activities = AipActivity::query()->with('aip.school')->whereHas('aip', fn ($query) => $query->where('fiscal_year', $year)->whereIn('school_id', $schoolIds))->orderBy('id')->get();
        $lines = $activities->map(fn (AipActivity $a) => [
            $a->aip->school?->name, $a->activity, $a->source_of_fund, ucfirst((string) $a->aip->status),
            (float) $a->q1_amount, (float) $a->q2_amount, (float) $a->q3_amount, (float) $a->q4_amount,
            round((float) $a->q1_amount + (float) $a->q2_amount + (float) $a->q3_amount + (float) $a->q4_amount, 2),
        ])->all();

        return [['School', 'Activity', 'Source of fund', 'AIP status', '1st quarter', '2nd quarter', '3rd quarter', '4th quarter', 'Total'], $this->withTotal($lines, [4, 5, 6, 7, 8], 9), [4, 5, 6, 7, 8]];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, int>} */
    private function allotmentRegistry(int $year, $schoolIds): array
    {
        $service = app(AllotmentRegistryService::class);
        $lines = BudgetAllocation::with('school')->where('fiscal_year', $year)->whereIn('school_id', $schoolIds)->orderBy('source_of_fund')->orderBy('uacs_code')->get()
            ->map(fn (BudgetAllocation $line) => $service->buildLine($line, 0))
            ->filter(fn (array $built) => $built['totals']['allotment'] > 0 || $built['totals']['obligation'] > 0)
            ->map(fn (array $built) => [
                $built['line']->school?->name, $built['line']->source_of_fund, $built['line']->uacs_code, $built['line']->particulars,
                (float) $built['totals']['allotment'], (float) $built['totals']['obligation'], (float) $built['totals']['disbursement'], (float) $built['totals']['not_obligated'], (float) $built['totals']['unpaid'],
            ])->values()->all();

        return [['School', 'Fund source', 'UACS code', 'Expense item', 'Allotment', 'Obligation', 'Disbursement', 'Allotment not yet obligated', 'Unpaid obligation'], $this->withTotal($lines, [4, 5, 6, 7, 8], 9), [4, 5, 6, 7, 8]];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, int>} */
    private function chartOfAccounts(): array
    {
        $lines = ChartOfAccount::query()->orderBy('code')->get()->map(fn (ChartOfAccount $account) => [$account->code, $account->title, $account->category])->all();

        return [['Code', 'Account title', 'Category'], $lines, []];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, int>} */
    private function accounting(int $year, $schoolIds): array
    {
        $lines = LiquidationReport::query()->with('school')->whereIn('school_id', $schoolIds)->whereYear('created_at', $year)->orderBy('created_at')->get()->map(fn (LiquidationReport $r) => [
            $r->school?->name, $r->ors_number ?: '-', $r->dv_number ? LiquidationReport::dvLabel($r->dv_number) : '-', $r->dv_date?->format('Y-m-d') ?? '-',
            $r->payee, $r->dv_non_cash ? 'Non-cash' : 'Cash', ucwords(str_replace('_', ' ', (string) $r->status)), (float) $r->amount,
        ])->all();

        // A non-cash DV (an advance received) is not an obligation: it is listed but left out of the total.
        $total = $lines === [] ? [] : [['TOTAL (non-cash DVs not counted)', '', '', '', '', '', '', round(array_sum(array_column(array_filter($lines, fn (array $line) => $line[5] !== 'Non-cash'), 7)), 2)]];

        return [['School', 'ORS No.', 'DV No.', 'DV date', 'Payee', 'Type', 'Status', 'Amount'], [...$lines, ...$total], [7]];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: array<int, int>} */
    private function cash(int $year, $schoolIds): array
    {
        $cash = app(CashReportService::class);
        $lines = LiquidationReport::query()->with('school')->whereIn('school_id', $schoolIds)->where('status', 'approved')->where('dv_non_cash', false)->whereNotNull('dv_number')->whereNotNull('paid_at')->whereYear('paid_at', $year)->orderBy('paid_at')->get()
            ->map(fn (LiquidationReport $r) => [
                $r->school?->name, $r->paid_at->format('Y-m-d'), LiquidationReport::dvLabel($r->dv_number), $r->payment_mode ?: '-', $r->payment_reference ?: '-', $r->payee,
                (float) $r->amount, $cash->deductionsOf($r), round((float) $r->amount - $cash->deductionsOf($r), 2),
            ])->all();

        return [['School', 'Date paid', 'DV No.', 'Mode', 'Check / ADA reference', 'Payee', 'Gross', 'Tax withheld', 'Cash paid'], $this->withTotal($lines, [6, 7, 8], 9), [6, 7, 8]];
    }
}
