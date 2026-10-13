<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\Aip;
use App\Models\ChartOfAccount;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Services\AppCseService;
use App\Services\SobService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The School Operating Budget of a quarter: pick AIP activities, add items, approve. The rules live in SobService. */
class SobController extends Controller
{
    public function __construct(private SobService $sob, private AppCseService $cse) {}

    /** The Common Office Supply particulars of the approved SOBs go to the APP-CSE of the year; written again after every change of an SOB. */
    private function syncCse(Request $request, SobPlan $plan): void
    {
        $this->cse->syncFromSob(School::withoutGlobalScopes()->findOrFail($plan->school_id), (int) $plan->fiscal_year, $request->user());
    }

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
        $over = collect($this->sob->overAip($sobPlan));

        return view('sob-show', [
            'plan' => $sobPlan,
            'grouped' => $this->sob->grouped($sobPlan),
            'summary' => $this->sob->summaryByAccount($sobPlan),
            'over' => $over,
            'accounts' => ChartOfAccount::where('organization_id', $sobPlan->organization_id)->orderBy('code')->get(),
            'canManage' => $request->user()->hasPermission('planning.manage'),
            'fundChoices' => FundSource::where('organization_id', $sobPlan->organization_id)->where('is_active', true)->orderBy('name')->pluck('name')
                ->merge(collect(Aip::FUNDS)->flatten())->push($sobPlan->fund_source)->unique()->sort()->values(),
        ]);
    }

    public function print(Request $request, SobPlan $sobPlan): View
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $this->scoped($request, $sobPlan);
        $sobPlan->load('school');

        return view('sob-print', [
            'plan' => $sobPlan,
            'school' => $sobPlan->school,
            'agency' => AgencySetting::first(),
            'grouped' => $this->sob->grouped($sobPlan),
            'summary' => $this->sob->summaryByAccount($sobPlan),
        ]);
    }

    public function update(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->updateHeader($sobPlan, $request->only(['fund_source', 'quarter', 'school_id_number', 'prepared_by_name', 'prepared_by_position', 'prepared_by_role', 'recommended_by_name', 'recommended_by_position', 'recommended_by_role', 'approved_by_name', 'approved_by_position', 'approved_by_role']));
        $this->syncCse($request, $sobPlan->fresh());

        return back()->with('success', 'SOB details saved.');
    }

    public function destroy(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->deletePlan($sobPlan);
        $this->syncCse($request, $sobPlan);

        return redirect(route('planning', ['school_id' => $sobPlan->school_id, 'year' => $sobPlan->fiscal_year]).'#sob')->with('success', 'The SOB was deleted.');
    }

    public function approve(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $result = $this->sob->approve($sobPlan, $request->user());
        $this->syncCse($request, $sobPlan);

        return back()->with('success', 'SOB approved. Budget allotments created: '.$result['created'].', updated: '.$result['updated'].'. See the Allotment Registry.');
    }

    public function reopen(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->reopen($sobPlan, $request->user());
        $this->syncCse($request, $sobPlan);

        return back()->with('success', 'The SOB is a draft again. Make your changes, then approve it to update the budget lines.');
    }

    public function storeItem(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        if (is_array($request->input('lines'))) {
            $items = $this->sob->addItems($sobPlan, (string) $request->input('aip_activity_id'), (string) $request->input('chart_of_account_id'), $request->input('lines'), $request->input('note'), (array) $request->input('extras', []));
            $this->syncCse($request, $sobPlan);

            return back()->with('success', count($items) === 1 ? 'Item added.' : count($items).' lines added.');
        }
        $this->sob->addItem($sobPlan, $request->all());
        $this->syncCse($request, $sobPlan);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, SobItem $sobItem): RedirectResponse
    {
        $this->scoped($request, $plan = $sobItem->plan()->firstOrFail());
        $this->sob->updateItem($sobItem, $request->all());
        $this->syncCse($request, $plan);

        return back()->with('success', 'Item saved.');
    }

    public function destroyItem(Request $request, SobItem $sobItem): RedirectResponse
    {
        $this->scoped($request, $plan = $sobItem->plan()->firstOrFail());
        $this->sob->deleteItem($sobItem);
        $this->syncCse($request, $plan);

        return back()->with('success', 'Item removed.');
    }

    public function updateGroup(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->saveGroup($sobPlan, (int) $request->input('old_activity'), (int) $request->input('old_account'), $request->only(['aip_activity_id', 'chart_of_account_id', 'note', 'lines', 'extras']));
        $this->syncCse($request, $sobPlan);

        return back()->with('success', 'Item saved.');
    }

    public function destroyGroup(Request $request, SobPlan $sobPlan): RedirectResponse
    {
        $this->scoped($request, $sobPlan);
        $this->sob->deleteGroup($sobPlan, (int) $request->input('old_activity'), (int) $request->input('old_account'));
        $this->syncCse($request, $sobPlan);

        return back()->with('success', 'Item removed.');
    }

    /** The official SOB drawn from an entry that is not saved yet: the entry is made inside a transaction that is rolled back. An entry that does not pass is left out of the drawing. */
    public function previewItem(Request $request, SobPlan $sobPlan)
    {
        return $this->previewWith($request, $sobPlan, fn () => $this->storeItem($request, $sobPlan));
    }

    public function previewItemUpdate(Request $request, SobItem $sobItem)
    {
        return $this->previewWith($request, $sobItem->plan()->firstOrFail(), fn () => $this->updateItem($request, $sobItem));
    }

    public function previewGroup(Request $request, SobPlan $sobPlan)
    {
        return $this->previewWith($request, $sobPlan, fn () => $this->updateGroup($request, $sobPlan));
    }

    private function previewWith(Request $request, SobPlan $plan, Closure $apply)
    {
        DB::beginTransaction();
        try {
            try {
                $apply();
            } catch (\Throwable) {
                // the saved form is drawn instead
            }
            session()->forget(['success', 'errors', '_old_input']);

            return response($this->print($request, $plan->fresh())->render());
        } finally {
            DB::rollBack();
        }
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
