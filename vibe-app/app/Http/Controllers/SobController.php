<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\School;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The School Operating Budget of a quarter: pick AIP activities, add items, approve. The rules live in SobService. */
class SobController extends Controller
{
    public function __construct(private SobService $sob) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds($request))],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'quarter' => ['required', 'integer', 'between:1,4'],
            'fund_source' => ['required', 'string', 'max:100'],
        ]);
        $school = School::query()->findOrFail($data['school_id']);
        $plan = $this->sob->create($school, (int) $data['fiscal_year'], (int) $data['quarter'], $data['fund_source'], $request->user());

        return redirect()->route('planning.sob.show', $plan)->with('success', 'The '.$plan->quarterLabel().' SOB was created. Pick the AIP activities that have a budget and add their items.');
    }

    public function show(Request $request, SobPlan $sobPlan): View
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $this->scoped($request, $sobPlan);
        $sobPlan->load(['school', 'aip.kras.activities']);
        $over = collect($this->sob->overAip($sobPlan))->keyBy('aip_activity_id');

        return view('sob-show', [
            'plan' => $sobPlan,
            'grouped' => $this->sob->grouped($sobPlan),
            'summary' => $this->sob->summaryByAccount($sobPlan),
            'over' => $over,
            'accounts' => ChartOfAccount::where('organization_id', $sobPlan->organization_id)->orderBy('code')->get(),
            'canManage' => $request->user()->hasPermission('planning.manage'),
        ]);
    }

    public function update(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->updateHeader($sobPlan, $request->only(['fund_source', 'prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position']));

        return back()->with('success', 'SOB details saved.');
    }

    public function destroy(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->deletePlan($sobPlan);

        return redirect(route('planning', ['school_id' => $sobPlan->school_id, 'year' => $sobPlan->fiscal_year]).'#sob')->with('success', 'The draft SOB was deleted.');
    }

    public function approve(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $result = $this->sob->approve($sobPlan, $request->user());

        return back()->with('success', 'SOB approved. Budget allotments created: '.$result['created'].', updated: '.$result['updated'].'. See the Allotment Registry.');
    }

    public function storeItem(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->addItem($sobPlan, $request->all());

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, SobItem $sobItem): RedirectResponse
    {
        $this->scoped($request, $sobItem->plan()->firstOrFail());
        $this->sob->updateItem($sobItem, $request->all());

        return back()->with('success', 'Item saved.');
    }

    public function destroyItem(Request $request, SobItem $sobItem): RedirectResponse
    {
        $this->scoped($request, $sobItem->plan()->firstOrFail());
        $this->sob->deleteItem($sobItem);

        return back()->with('success', 'Item removed.');
    }

    /** @return array<int, int> the schools this user may work with (the same rule as the Planning page) */
    private function schoolIds(Request $request): array
    {
        $user = $request->user();

        return ($user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id'))->all();
    }

    private function scoped(Request $request, SobPlan $plan): void
    {
        abort_unless(in_array($plan->school_id, $this->schoolIds($request), true), 403);
    }
}
