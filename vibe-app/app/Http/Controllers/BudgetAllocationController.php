<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetAllocationController extends Controller
{
    public const REPORTS = [
        'annual' => 'Annual Budget Allocation Report',
        'quarterly' => 'Quarterly Budget Utilization Report',
        'budget-vs-obligation' => 'Budget vs Obligation Report',
        'budget-vs-liquidation' => 'Budget vs Liquidation Report',
        'balance' => 'Remaining Budget Balance Report',
        'fund-source' => 'Fund Source Summary Report',
        'expense-item' => 'Expense Item Summary Report',
        'office' => 'Office/Department Budget Report',
        'alerts' => 'Over Budget / Near Limit Alert Report',
        'unliquidated' => 'Obligated but Not Yet Liquidated Report',
        'liquidated' => 'Liquidated Expenses Report',
        'chart-of-accounts' => 'Chart of Accounts Budget Report',
    ];


    public function __construct(private BudgetService $budget)
    {
    }

    private function schoolIds()
    {
        $user = request()->user();

        return $user->role === 'master_user' || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    private function authorizeItem(BudgetAllocation $item): void
    {
        abort_unless($this->schoolIds()->contains($item->school_id), 403);
        $user = request()->user();
        abort_if($user->role === 'office_user' && $user->office && $item->office !== $user->office, 403);
    }

    private function authorizeManage(): void
    {
        abort_unless(request()->user()->canManageBudget(), 403, 'Only the Budget Officer or an administrator can change budget allocations.');
    }

    private function accounts()
    {
        $user = request()->user();
        ChartOfAccount::ensureDefaults($user->organization_id);

        return ChartOfAccount::query()
            ->when($user->role === 'master_user' && !$user->organization_id, fn ($q) => $q->whereNull('organization_id'))
            ->orderBy('code')->get();
    }

    /** Items for the selected filters, with their quarterly matrix. */
    private function rows(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);
        $schoolId = $request->query('school_id');
        $schoolIds = $this->schoolIds();
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $items = BudgetAllocation::with(['school', 'account'])->where('fiscal_year', $year)->whereIn('school_id', $schoolIds)
            ->when($schoolId && $schoolIds->contains((int) $schoolId), fn ($q) => $q->where('school_id', $schoolId))
            ->when($request->query('fund'), fn ($q, $fund) => $q->where('source_of_fund', $fund))
            ->when($request->query('office'), fn ($q, $office) => $q->where('office', $office))
            ->when($user->role === 'office_user' && $user->office, fn ($q) => $q->where('office', $user->office))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('particulars', 'like', "%{$search}%")->orWhere('uacs_code', 'like', "%{$search}%")
                ->orWhere('office', 'like', "%{$search}%")->orWhere('program', 'like', "%{$search}%")->orWhere('source_of_fund', 'like', "%{$search}%")))
            ->orderBy('office')->orderBy('source_of_fund')->orderBy('uacs_code')->get();

        return [$year, $schoolId, $this->budget->matrix($items)];
    }

    private function summaries($rows): array
    {
        $sum = fn ($set) => [
            'allocated' => $set->sum('allocated'), 'obligated' => $set->sum('obligated'), 'liquidated' => $set->sum('liquidated'),
            'balance' => $set->sum('balance'), 'unliquidated' => $set->sum('unliquidated'),
        ];

        $quarters = collect([1, 2, 3, 4])->mapWithKeys(fn ($q) => [$q => [
            'allocated' => $rows->sum(fn ($r) => $r['quarters'][$q]['allocated']),
            'obligated' => $rows->sum(fn ($r) => $r['quarters'][$q]['obligated']),
            'liquidated' => $rows->sum(fn ($r) => $r['quarters'][$q]['liquidated']),
            'balance' => $rows->sum(fn ($r) => $r['quarters'][$q]['balance']),
        ]]);

        return [
            'counts' => [
                'fully' => $rows->where('status', 'Fully Obligated')->count(),
                'near' => $rows->where('status', 'Near Limit')->count(),
                'available' => $rows->where('status', 'Available')->count(),
            ],
            'totals' => $sum($rows) + ['usage' => $rows->sum('allocated') > 0 ? round($rows->sum('obligated') / $rows->sum('allocated') * 100, 1) : 0],
            'quarters' => $quarters,
            'funds' => $rows->groupBy(fn ($r) => $r['item']->source_of_fund)->map($sum),
            'categories' => $rows->groupBy(fn ($r) => $r['item']->account?->category ?? 'Uncategorized')->map($sum),
        ];
    }

    public function index(Request $request)
    {
        [$year, $schoolId, $rows] = $this->rows($request);

        return view('budget-allocation', [
            'year' => $year,
            'selectedSchoolId' => $schoolId,
            'schools' => School::whereIn('id', $this->schoolIds())->orderBy('name')->get(),
            'rows' => $rows,
            'summary' => $this->summaries($rows),
            'reports' => self::REPORTS,
            'filters' => $request->only(['q', 'fund', 'office', 'quarter']),
            'funds' => BudgetAllocation::whereIn('school_id', $this->schoolIds())->distinct()->pluck('source_of_fund')->merge(collect(\App\Models\Aip::FUNDS)->flatten()->reject(fn ($fund) => $fund === 'Others'))->unique()->sort()->values(),
            'offices' => BudgetAllocation::whereIn('school_id', $this->schoolIds())->whereNotNull('office')->distinct()->orderBy('office')->pluck('office'),
            'canManage' => $request->user()->canManageBudget(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeManage();
        return view('budget-item-form', $this->formData(new BudgetAllocation([
            'fiscal_year' => (int) $request->query('year', now()->year),
            'school_id' => $request->query('school_id'),
            'source_of_fund' => 'MOOE',
        ])));
    }

    public function edit(BudgetAllocation $budgetAllocation)
    {
        $this->authorizeManage();
        $this->authorizeItem($budgetAllocation);
        abort_if($budgetAllocation->closed_at, 403, 'This budget item is closed.');

        return view('budget-item-form', $this->formData($budgetAllocation));
    }

    private function formData(BudgetAllocation $item): array
    {
        return [
            'item' => $item,
            'schools' => School::whereIn('id', $this->schoolIds())->orderBy('name')->get(),
            'accounts' => $this->accounts(),
            'offices' => BudgetAllocation::whereIn('school_id', $this->schoolIds())->whereNotNull('office')->distinct()->orderBy('office')->pluck('office'),
            'funds' => BudgetAllocation::query()->distinct()->pluck('source_of_fund')->merge(['MOOE', 'Special Education Fund', 'General Fund', 'Canteen Fund'])->merge(collect(\App\Models\Aip::FUNDS)->flatten()->reject(fn ($fund) => $fund === 'Others'))->unique()->values(),
        ];
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validated($request);
        $item = BudgetAllocation::create(array_merge($data, ['created_by' => $request->user()->id, 'budget_ref_no' => ($data['budget_ref_no'] ?? null) ?: $this->nextReference((int) $data['fiscal_year'])]));
        $this->audit($request, $item, 'budget_item_created');

        return redirect()->route('budget.allocation', ['year' => $item->fiscal_year, 'school_id' => $item->school_id])->with('success', 'Budget item saved.');
    }

    public function update(Request $request, BudgetAllocation $budgetAllocation)
    {
        $this->authorizeManage();
        $this->authorizeItem($budgetAllocation);
        abort_if($budgetAllocation->closed_at, 403, 'This budget item is closed.');
        $data = $this->validated($request, $budgetAllocation);

        $matrix = $this->budget->matrix(collect([$budgetAllocation]))->first();
        if ((float) $data['amount'] + 0.001 < $matrix['obligated']) {
            throw ValidationException::withMessages(['amount' => sprintf('Annual allocation cannot be lower than the ₱%s already obligated.', number_format($matrix['obligated'], 2))]);
        }

        $budgetAllocation->update(array_merge($data, ['budget_ref_no' => ($data['budget_ref_no'] ?? null) ?: $budgetAllocation->budget_ref_no]));
        $this->audit($request, $budgetAllocation, 'budget_item_updated');

        return redirect()->route('budget.allocation', ['year' => $budgetAllocation->fiscal_year, 'school_id' => $budgetAllocation->school_id])->with('success', 'Budget item updated.');
    }

    public function show(BudgetAllocation $budgetAllocation)
    {
        $this->authorizeItem($budgetAllocation);
        $budgetAllocation->load(['school', 'account']);
        $row = $this->budget->matrix(collect([$budgetAllocation]))->first();

        $transactions = ProcurementRequest::with('liquidationReports')->where('budget_allocation_id', $budgetAllocation->id)->get()
            ->map(fn ($pr) => ['date' => $pr->created_at, 'type' => 'PR', 'ref' => $pr->request_number, 'description' => $pr->title, 'obligated' => (float) $pr->amount, 'liquidated' => (float) $pr->liquidationReports->where('status', 'approved')->sum('amount'), 'status' => $pr->status, 'url' => route('procurement.edit', $pr)])
            ->concat(LiquidationReport::whereNull('procurement_request_id')->where('budget_allocation_id', $budgetAllocation->id)->get()
                ->map(fn ($o) => ['date' => $o->created_at, 'type' => 'ORS', 'ref' => $o->ors_number, 'description' => $o->purpose, 'obligated' => (float) $o->amount, 'liquidated' => $o->status === 'approved' ? (float) $o->amount : 0.0, 'status' => $o->status, 'url' => route('liquidation', ['search' => $o->ors_number])]))
            ->sortByDesc('date')->values();

        return view('budget-item-show', ['item' => $budgetAllocation, 'row' => $row, 'transactions' => $transactions]);
    }

    public function destroy(Request $request, BudgetAllocation $budgetAllocation)
    {
        $this->authorizeManage();
        $this->authorizeItem($budgetAllocation);
        if (ProcurementRequest::where('budget_allocation_id', $budgetAllocation->id)->exists() || LiquidationReport::where('budget_allocation_id', $budgetAllocation->id)->exists()) {
            return back()->withErrors(['budget' => 'This budget item has obligations or liquidations linked to it and cannot be deleted.']);
        }

        $this->audit($request, $budgetAllocation, 'budget_item_deleted');
        $budgetAllocation->delete();

        return redirect()->route('budget.allocation', ['year' => $budgetAllocation->fiscal_year])->with('success', 'Budget item removed.');
    }

    public function close(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate(['year' => ['required', 'integer'], 'school_id' => ['nullable', 'integer']]);
        $count = BudgetAllocation::where('fiscal_year', $data['year'])->whereNull('closed_at')->whereIn('school_id', $this->schoolIds())
            ->when($data['school_id'] ?? null, fn ($q, $id) => $q->where('school_id', $id))->update(['closed_at' => now()]);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'budget_period_closed', 'metadata' => ['fiscal_year' => $data['year'], 'items' => $count]]);

        return redirect()->route('budget.allocation', ['year' => $data['year'], 'school_id' => $data['school_id'] ?? null])->with('success', "Budget period FY {$data['year']} closed ({$count} items).");
    }

    private function validated(Request $request, ?BudgetAllocation $existing = null): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'budget_ref_no' => ['nullable', 'string', 'max:50', Rule::unique('budget_allocations', 'budget_ref_no')->ignore($existing?->id)],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'source_of_fund' => ['required', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            'fund_name' => ['nullable', 'string', 'max:255'],
            'responsibility_center' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
            'distribution' => ['required', Rule::in(['equal', 'manual'])],
            'q1_amount' => ['nullable', 'numeric', 'min:0'],
            'q2_amount' => ['nullable', 'numeric', 'min:0'],
            'q3_amount' => ['nullable', 'numeric', 'min:0'],
            'q4_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = round((float) $data['amount'], 2);
        if ($data['distribution'] === 'equal') {
            $quarter = round($amount / 4, 2);
            $data['q1_amount'] = $data['q2_amount'] = $data['q3_amount'] = $quarter;
            $data['q4_amount'] = round($amount - $quarter * 3, 2);
        } else {
            $total = round(collect([1, 2, 3, 4])->sum(fn ($q) => (float) ($data["q{$q}_amount"] ?? 0)), 2);
            if (abs($total - $amount) > 0.009) {
                throw ValidationException::withMessages(['q1_amount' => sprintf('Quarterly allocations total ₱%s but the annual allocation is ₱%s.', number_format($total, 2), number_format($amount, 2))]);
            }
            foreach ([1, 2, 3, 4] as $q) {
                $data["q{$q}_amount"] = (float) ($data["q{$q}_amount"] ?? 0);
            }
        }
        unset($data['distribution']);

        $account = ChartOfAccount::withoutGlobalScopes()->findOrFail($data['chart_of_account_id']);
        $data['uacs_code'] = $account->code;
        $data['particulars'] = $account->title;
        $data['amount'] = $amount;
        $data['start_date'] ??= $data['fiscal_year'] . '-01-01';
        $data['end_date'] ??= $data['fiscal_year'] . '-12-31';

        return $data;
    }

    private function nextReference(int $year): string
    {
        $next = BudgetAllocation::withoutGlobalScopes()->where('fiscal_year', $year)->count() + 1;
        do {
            $reference = sprintf('BA-%d-%04d', $year, $next++);
        } while (BudgetAllocation::withoutGlobalScopes()->where('budget_ref_no', $reference)->exists());

        return $reference;
    }

    private function audit(Request $request, BudgetAllocation $item, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'school_id' => $item->school_id, 'action' => $action,
            'auditable_type' => BudgetAllocation::class, 'auditable_id' => $item->id,
            'metadata' => ['fund' => $item->source_of_fund, 'item' => $item->particulars, 'fiscal_year' => $item->fiscal_year, 'amount' => $item->amount],
        ]);
    }

    public function report(Request $request, string $type)
    {
        abort_unless(isset(self::REPORTS[$type]), 404);
        [$year, $schoolId, $rows] = $this->rows($request);
        [$columns, $lines, $money] = $this->buildReport($type, $rows);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($columns, $lines) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $columns);
                foreach ($lines as $line) {
                    fputcsv($out, $line);
                }
                fclose($out);
            }, "budget-{$type}-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('budget-report', [
            'title' => self::REPORTS[$type], 'year' => $year, 'columns' => $columns, 'lines' => $lines, 'money' => $money,
            'type' => $type, 'schoolName' => $schoolId ? School::find($schoolId)?->name : null, 'reports' => self::REPORTS, 'selectedSchoolId' => $schoolId,
        ]);
    }

    /** @return array{0: array<int,string>, 1: array<int,array<int,mixed>>, 2: array<int,int>} columns, rows, and column indexes shown as pesos */
    private function buildReport(string $type, $rows): array
    {
        $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1) . '%' : '0%';
        $name = fn ($r) => $r['item']->particulars;
        $code = fn ($r) => $r['item']->uacs_code;
        $fund = fn ($r) => $r['item']->source_of_fund;

        switch ($type) {
            case 'annual':
                $lines = $rows->map(fn ($r) => [$r['item']->office, $fund($r), $code($r), $name($r), $r['allocated'], $r['quarters'][1]['allocated'], $r['quarters'][2]['allocated'], $r['quarters'][3]['allocated'], $r['quarters'][4]['allocated']])->all();

                return [['Office/Department', 'Fund Source', 'Account Code', 'Expense Item', 'Annual Allocation', 'Q1', 'Q2', 'Q3', 'Q4'], $this->withTotal($lines, 4, 8), [4, 5, 6, 7, 8]];
            case 'quarterly':
                $lines = [];
                foreach ($rows as $r) {
                    foreach ($r['quarters'] as $q => $v) {
                        $lines[] = [$name($r), "Q{$q}", $v['allocated'], $v['obligated'], $v['liquidated'], $v['balance'], $pct($v['obligated'], $v['allocated'])];
                    }
                }

                return [['Expense Item', 'Quarter', 'Allocated', 'Obligated', 'Liquidated', 'Balance', 'Utilization'], $lines, [2, 3, 4, 5]];
            case 'budget-vs-obligation':
                $lines = $rows->map(fn ($r) => [$r['item']->office, $fund($r), $code($r), $name($r), $r['allocated'], $r['obligated'], $r['balance'], $pct($r['obligated'], $r['allocated'])])->all();

                return [['Office/Department', 'Fund Source', 'Account Code', 'Expense Item', 'Allocation', 'Obligated', 'Remaining', 'Obligated %'], $this->withTotal($lines, 4, 6), [4, 5, 6]];
            case 'budget-vs-liquidation':
                $lines = $rows->map(fn ($r) => [$r['item']->office, $fund($r), $code($r), $name($r), $r['allocated'], $r['liquidated'], $r['allocated'] - $r['liquidated'], $pct($r['liquidated'], $r['allocated'])])->all();

                return [['Office/Department', 'Fund Source', 'Account Code', 'Expense Item', 'Allocation', 'Liquidated', 'Unused', 'Liquidated %'], $this->withTotal($lines, 4, 6), [4, 5, 6]];
            case 'expense-item':
                $lines = $rows->groupBy($code)->map(fn ($g, $c) => [$c, $g->first()['item']->particulars, $g->count(), $g->sum('allocated'), $g->sum('obligated'), $g->sum('liquidated'), $g->sum('balance'), $pct($g->sum('obligated'), $g->sum('allocated'))])->values()->all();

                return [['Account Code', 'Expense Item', 'Budget Lines', 'Allocated', 'Obligated', 'Liquidated', 'Balance', 'Used'], $this->withTotal($lines, 2, 6), [3, 4, 5, 6]];
            case 'office':
                $lines = $rows->groupBy(fn ($r) => $r['item']->office)->map(fn ($g, $o) => [$o, $g->count(), $g->sum('allocated'), $g->sum('obligated'), $g->sum('liquidated'), $g->sum('balance'), $pct($g->sum('obligated'), $g->sum('allocated'))])->values()->all();

                return [['Office/Department', 'Budget Lines', 'Allocated', 'Obligated', 'Liquidated', 'Balance', 'Used'], $this->withTotal($lines, 1, 5), [2, 3, 4, 5]];
            case 'alerts':
                $lines = $rows->filter(fn ($r) => in_array($r['status'], ['Near Limit', 'Fully Obligated'], true) || $r['warnings'])
                    ->map(fn ($r) => [$r['item']->office, $fund($r), $code($r), $name($r), $r['allocated'], $r['obligated'], $r['balance'], $pct($r['obligated'], $r['allocated']), $r['warnings'] ? implode('; ', $r['warnings']) : $r['status']])->values()->all();

                return [['Office/Department', 'Fund Source', 'Account Code', 'Expense Item', 'Allocation', 'Obligated', 'Remaining', 'Used', 'Alert'], $lines, [4, 5, 6]];
            case 'unliquidated':
                $lines = [];
                foreach ($rows as $r) {
                    foreach (ProcurementRequest::with('liquidationReports')->where('budget_allocation_id', $r['item']->id)->get() as $pr) {
                        $done = (float) $pr->liquidationReports->where('status', 'approved')->sum('amount');
                        if ($pr->amount - $done > 0.001) {
                            $lines[] = [$pr->created_at->format('Y-m-d'), 'PR', $pr->request_number, $name($r), (float) $pr->amount, $done, (float) $pr->amount - $done];
                        }
                    }
                    foreach (LiquidationReport::whereNull('procurement_request_id')->where('budget_allocation_id', $r['item']->id)->where('status', '!=', 'approved')->get() as $o) {
                        $lines[] = [$o->created_at->format('Y-m-d'), 'ORS', $o->ors_number, $name($r), (float) $o->amount, 0.0, (float) $o->amount];
                    }
                }

                return [['Date', 'Type', 'Reference', 'Expense Item', 'Obligated', 'Liquidated', 'Unliquidated'], $this->withTotal($lines, 4, 6), [4, 5, 6]];
            case 'liquidated':
                $lines = [];
                foreach ($rows as $r) {
                    $reports = LiquidationReport::where('status', 'approved')->where(fn ($q) => $q->where('budget_allocation_id', $r['item']->id)
                        ->orWhereIn('procurement_request_id', ProcurementRequest::where('budget_allocation_id', $r['item']->id)->select('id')))->get();
                    foreach ($reports as $o) {
                        $lines[] = [$o->created_at->format('Y-m-d'), $o->ors_number, $name($r), $o->payee ?: '—', $o->purpose, (float) $o->amount];
                    }
                }

                return [['Date', 'ORS No.', 'Expense Item', 'Payee', 'Purpose', 'Amount'], $this->withTotal($lines, 5, 5), [5]];
            case 'balance':
                $lines = $rows->map(fn ($r) => [$fund($r), $name($r), $r['allocated'], $r['obligated'], $r['balance'], $pct($r['obligated'], $r['allocated']), $r['status']])->all();

                return [['Fund Source', 'Expense Item', 'Allocated', 'Obligated', 'Remaining Balance', 'Used', 'Status'], $this->withTotal($lines, 2, 4), [2, 3, 4]];
            case 'fund-source':
                $lines = $rows->groupBy($fund)->map(fn ($g, $f) => [$f, $g->count(), $g->sum('allocated'), $g->sum('obligated'), $g->sum('liquidated'), $g->sum('balance'), $pct($g->sum('obligated'), $g->sum('allocated'))])->values()->all();

                return [['Fund Source', 'Items', 'Allocated', 'Obligated', 'Liquidated', 'Balance', 'Used'], $this->withTotal($lines, 1, 5), [2, 3, 4, 5]];
            default:
                $lines = $rows->groupBy($code)->map(fn ($g, $c) => [$c, $g->first()['item']->particulars, $g->first()['item']->account?->category, $g->sum('allocated'), $g->sum('obligated'), $g->sum('liquidated'), $g->sum('balance')])->values()->all();

                return [['Account Code', 'Account Title', 'Category', 'Allocated', 'Obligated', 'Liquidated', 'Balance'], $this->withTotal($lines, 3, 6), [3, 4, 5, 6]];
        }
    }

    /** Append a TOTAL row summing numeric columns $from..$to. */
    private function withTotal(array $lines, int $from, int $to): array
    {
        if (!$lines) {
            return $lines;
        }

        $total = array_fill(0, count($lines[0]), '');
        $total[0] = 'TOTAL';
        for ($i = $from; $i <= $to; $i++) {
            $total[$i] = array_sum(array_column($lines, $i));
        }

        return array_merge($lines, [$total]);
    }
}
