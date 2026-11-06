<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Supplier;
use App\Services\BudgetService;
use App\Support\SignatorySlots;
use Illuminate\Http\Request;

class BudgetController extends Controller
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
        $schoolIds = $this->schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $year = (int) $request->query('year', now()->year);
        $selectedSchoolId = $request->query('school_id');
        $schoolFilter = fn ($q) => $q->whereIn('school_id', $schoolIds)
            ->when($selectedSchoolId && $schoolIds->contains((int) $selectedSchoolId), fn ($q) => $q->where('school_id', $selectedSchoolId));

        $allocations = BudgetAllocation::with('school')->where('fiscal_year', $year)->tap($schoolFilter)->latest()->get();
        // Only an actual obligation counts: a procurement request is not obligated until it has an ORS (owner's rule 2026-11-06).
        $obligations = LiquidationReport::withOrs()->with('procurementRequest')->whereYear('created_at', $year)->tap($schoolFilter)->get();
        $fundOf = fn ($o) => $o->source_of_fund ?: $o->procurementRequest?->source_of_fund;

        // Utilization per school + fund source.
        $lines = $allocations->groupBy(fn ($a) => $a->school_id.'|'.$a->source_of_fund)->map(function ($group) use ($obligations, $fundOf) {
            $first = $group->first();
            $matching = $obligations->filter(fn ($o) => $o->school_id === $first->school_id && $fundOf($o) === $first->source_of_fund);
            $allocated = $group->sum('amount');
            $obligated = $matching->sum('amount');
            $liquidated = $matching->where('status', 'approved')->sum('amount');

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

        $isUnbudgeted = fn ($r) => ! $allocations->contains(fn ($a) => $a->school_id === $r->school_id && $a->source_of_fund === $fundOf($r));
        $unbudgeted = $obligations->filter($isUnbudgeted)->sum('amount');

        $transactions = $obligations->map(fn ($o) => ['date' => $o->created_at, 'type' => 'ORS', 'ref' => $o->ors_number, 'description' => $o->purpose, 'school_id' => $o->school_id, 'fund' => $fundOf($o), 'amount' => $o->amount, 'status' => $o->status, 'url' => route('liquidation', ['search' => $o->ors_number])])
            ->sortByDesc('date')->values();

        $orsList = LiquidationReport::withOrs()->with(['school', 'procurementRequest.budgetAllocation', 'budgetAllocation'])->whereYear('created_at', $year)->tap($schoolFilter)->latest()->get();
        $availableProcurements = ProcurementRequest::with('school', 'documents', 'budgetAllocation')->whereIn('school_id', $schoolIds)->whereHas('documents', fn ($query) => $query->where('document_type', 'purchase_order'))->whereDoesntHave('liquidationReports')->latest()->get();

        return view('budget', [
            'nextOrsNumber' => LiquidationReport::nextOrsNumber(),
            'budgetItems' => app(BudgetService::class)->withAvailability(BudgetAllocation::whereIn('school_id', $schoolIds)->whereNull('closed_at')->where('fiscal_year', $year)->orderBy('particulars')->get()),
            'orsList' => $orsList,
            'availableProcurements' => $availableProcurements,
            'orsSuppliers' => Supplier::whereIn('school_id', $schoolIds)->where('status', 'active')->orderBy('business_name')->get(['id', 'school_id', 'business_name', 'business_address', 'tin']),
            'isMasterUser' => request()->user()->seesAllSchools(),
            'transactions' => $transactions,
            'schoolNames' => $schools->pluck('name', 'id'),
            'signatoryPanels' => SignatorySlots::panelsForSchools('obligation_request', $schools->pluck('id')),
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
    public function balance(Request $request, BudgetService $budget)
    {
        $schoolId = (int) $request->query('school_id');
        abort_unless($this->schoolIds()->contains($schoolId), 403);

        return response()->json($budget->position($schoolId, $request->query('fund')));
    }
}
