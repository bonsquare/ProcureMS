<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use Illuminate\Http\Request;

class BudgetController extends Controller
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
        $schoolIds = $this->schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $year = (int) $request->query('year', now()->year);
        $selectedSchoolId = $request->query('school_id');
        $schoolFilter = fn ($q) => $q->whereIn('school_id', $schoolIds)
            ->when($selectedSchoolId && $schoolIds->contains((int) $selectedSchoolId), fn ($q) => $q->where('school_id', $selectedSchoolId));

        $allocations = BudgetAllocation::with('school')->where('fiscal_year', $year)->tap($schoolFilter)->latest()->get();
        $obligations = ProcurementRequest::with('liquidationReports')->whereYear('created_at', $year)->tap($schoolFilter)->get();

        $directOrs = LiquidationReport::whereNull('procurement_request_id')->whereYear('created_at', $year)->tap($schoolFilter)->get();

        // Utilization per school + fund source.
        $lines = $allocations->groupBy(fn ($a) => $a->school_id . '|' . $a->source_of_fund)->map(function ($group) use ($obligations, $directOrs) {
            $first = $group->first();
            $matching = $obligations->where('school_id', $first->school_id)->where('source_of_fund', $first->source_of_fund);
            $direct = $directOrs->where('school_id', $first->school_id)->where('source_of_fund', $first->source_of_fund);
            $allocated = $group->sum('amount');
            $obligated = $matching->sum('amount') + $direct->sum('amount');
            $liquidated = $matching->flatMap->liquidationReports->where('status', 'approved')->sum('amount')
                + $direct->where('status', 'approved')->sum('amount');

            return [
                'school' => $first->school?->name,
                'fund' => $first->source_of_fund,
                'allocated' => $allocated,
                'obligated' => $obligated,
                'liquidated' => $liquidated,
                'balance' => $allocated - $obligated,
                'rate' => $allocated > 0 ? round($obligated / $allocated * 100, 1) : 0,
            ];
        })->values();

        $isUnbudgeted = fn ($r) => !$allocations->contains(fn ($a) => $a->school_id === $r->school_id && $a->source_of_fund === $r->source_of_fund);
        $unbudgeted = $obligations->filter($isUnbudgeted)->sum('amount') + $directOrs->filter($isUnbudgeted)->sum('amount');

        $transactions = $obligations->map(fn ($r) => ['date' => $r->created_at, 'type' => 'PR', 'ref' => $r->request_number, 'description' => $r->title, 'school_id' => $r->school_id, 'fund' => $r->source_of_fund, 'amount' => $r->amount, 'status' => $r->status, 'url' => route('procurement.edit', $r)])
            ->concat($directOrs->map(fn ($o) => ['date' => $o->created_at, 'type' => 'ORS (no PR)', 'ref' => $o->ors_number, 'description' => $o->purpose, 'school_id' => $o->school_id, 'fund' => $o->source_of_fund, 'amount' => $o->amount, 'status' => $o->status, 'url' => route('liquidation', ['search' => $o->ors_number])]))
            ->sortByDesc('date')->values();

        $orsList = LiquidationReport::with(['school', 'procurementRequest'])->whereYear('created_at', $year)->tap($schoolFilter)->latest()->get();
        $availableProcurements = ProcurementRequest::with('school', 'documents')->whereIn('school_id', $schoolIds)->whereDoesntHave('liquidationReports')->latest()->get();

        return view('budget', [
            'orsList' => $orsList,
            'availableProcurements' => $availableProcurements,
            'isMasterUser' => request()->user()->role === 'master_user',
            'transactions' => $transactions,
            'schoolNames' => $schools->pluck('name', 'id'),
            'schools' => $schools,
            'allocations' => $allocations,
            'lines' => $lines,
            'year' => $year,
            'selectedSchoolId' => $selectedSchoolId,
            'unbudgeted' => $unbudgeted,
            'totals' => [
                'allocated' => $lines->sum('allocated'),
                'obligated' => $lines->sum('obligated'),
                'liquidated' => $lines->sum('liquidated'),
                'balance' => $lines->sum('balance'),
            ],
        ]);
    }

    /** Live availability lookup used by the PR and ORS forms. */
    public function balance(Request $request, \App\Services\BudgetService $budget)
    {
        $schoolId = (int) $request->query('school_id');
        abort_unless($this->schoolIds()->contains($schoolId), 403);

        return response()->json($budget->position($schoolId, $request->query('fund')));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'source_of_fund' => ['required', 'string', 'max:255'],
            'uacs_code' => ['nullable', 'string', 'max:50'],
            'particulars' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
        ]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);

        $allocation = BudgetAllocation::create($data + ['created_by' => $request->user()->id]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'school_id' => $allocation->school_id,
            'action' => 'budget_allocated',
            'auditable_type' => BudgetAllocation::class,
            'auditable_id' => $allocation->id,
            'metadata' => ['fund' => $allocation->source_of_fund, 'fiscal_year' => $allocation->fiscal_year, 'amount' => $allocation->amount],
        ]);

        return redirect()->route('budget', ['year' => $data['fiscal_year']])->with('success', 'Budget allocation added.');
    }

    public function destroy(BudgetAllocation $budgetAllocation)
    {
        abort_unless($this->schoolIds()->contains($budgetAllocation->school_id), 403);
        $year = $budgetAllocation->fiscal_year;
        $budgetAllocation->delete();

        return redirect()->route('budget', ['year' => $year])->with('success', 'Budget allocation removed.');
    }
}
