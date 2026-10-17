<?php

namespace App\Http\Controllers;

use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\CseCatalogItem;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\SobPlan;
use App\Services\AppCseService;
use App\Support\SignatorySlots;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The APP-CSE of a school: create it for a fiscal year, add catalog and own items with their monthly quantities, approve. The rules live in AppCseService. */
class AppCseController extends Controller
{
    private const MONTHS = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'];

    private const HEADER = [
        'office_name', 'region', 'address', 'account_code', 'organization_type', 'contact_person', 'contact_position', 'contact_email', 'contact_phone',
        'approved_budget', 'approved_budget_words', 'date_prepared', 'agency_head_name', 'agency_head_position', 'signatories',
        'agency_head_role', 'prepared_by_role', 'prepared_by_name', 'prepared_by_position', 'noted_by_role', 'noted_by_name', 'noted_by_position',
        'certified_by_role', 'certified_by_name', 'certified_by_position',
    ];

    public function __construct(private AppCseService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds($request))],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $school = School::query()->findOrFail($data['school_id']);
        $plan = $this->service->create($school, (int) $data['fiscal_year'], $request->user());

        return redirect()->route('planning.app-cse.show', $plan)->with('success', 'The APP-CSE for FY '.$plan->fiscal_year.' was created. Add the catalog items you buy and their monthly quantities.');
    }

    public function show(Request $request, AppCsePlan $appCsePlan): View
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $this->scoped($request, $appCsePlan);
        $appCsePlan->load('school');
        $items = $appCsePlan->items()->with('catalogItem')->get();
        $query = trim((string) $request->query('q'));
        $added = $items->pluck('cse_catalog_item_id')->filter()->all();

        $results = $query === '' ? collect() : CseCatalogItem::where('catalog_year', $appCsePlan->catalog_year)
            ->where(fn ($where) => $where->where('description', 'like', '%'.$query.'%')->orWhere('code', 'like', '%'.$query.'%'))
            ->whereNotIn('id', $added)->orderBy('sort_order')->limit(25)->get();
        $staff = SchoolStaff::active()->where('school_id', $appCsePlan->school_id)->get();

        return view('app-cse-show', [
            'plan' => $appCsePlan,
            'items' => $items,
            'partOne' => $items->reject(fn (AppCseItem $item) => $item->isCustom())->values(),
            'partTwo' => $items->filter(fn (AppCseItem $item) => $item->isCustom())->values(),
            'totals' => $this->service->totals($appCsePlan),
            'hasQuantity' => $items->contains(fn (AppCseItem $item) => $item->totalQuantity() > 0),
            'query' => $query,
            'results' => $results->groupBy('category'),
            'signatoryRows' => SignatorySlots::panel('app_cse', $staff, $appCsePlan->signatories),
            'sobHistory' => SobPlan::where('school_id', $appCsePlan->school_id)->where('fiscal_year', $appCsePlan->fiscal_year)->orderByDesc('created_at')->get(),
            'canManage' => $request->user()->hasPermission('planning.manage'),
        ]);
    }

    /** Type-ahead for Part I: the catalog items of the plan's catalog year that match the typed words and are not in the plan yet. */
    public function catalog(Request $request, AppCsePlan $appCsePlan)
    {
        $this->scoped($request, $appCsePlan);
        $words = preg_split('/\s+/', trim((string) $request->query('q')), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === [] || mb_strlen(implode('', $words)) < 2) {
            return response()->json([]);
        }
        $query = CseCatalogItem::where('catalog_year', $appCsePlan->catalog_year)
            ->whereNotIn('id', $appCsePlan->items()->whereNotNull('cse_catalog_item_id')->pluck('cse_catalog_item_id'));
        foreach (array_slice($words, 0, 6) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($where) => $where->where('description', 'like', $like)->orWhere('code', 'like', $like)->orWhere('category', 'like', $like));
        }

        return response()->json($query->orderBy('sort_order')->limit(25)->get(['id', 'category', 'code', 'description', 'unit', 'price']));
    }

    /** The official form: every catalog row of the plan's catalog year, the plan's own items, the totals and the signatures. */
    public function print(Request $request, AppCsePlan $appCsePlan): View
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $this->scoped($request, $appCsePlan);
        $appCsePlan->load('school');
        $items = $appCsePlan->items()->get();
        $staff = SchoolStaff::active()->where('school_id', $appCsePlan->school_id)->get();
        $signers = SignatorySlots::overlay('app_cse', SignatorySlots::defaults('app_cse', $staff), $appCsePlan->signatories);

        return view('app-cse-print', [
            'plan' => $appCsePlan,
            'catalog' => CseCatalogItem::where('catalog_year', $appCsePlan->catalog_year)->orderBy('sort_order')->get(),
            'byCatalog' => $items->reject(fn (AppCseItem $item) => $item->isCustom())->keyBy('cse_catalog_item_id'),
            'partTwo' => $items->filter(fn (AppCseItem $item) => $item->isCustom())->values(),
            'totals' => $this->service->totals($appCsePlan),
            'preparedBy' => $signers['preparedBy'] ?? null,
            'notedBy' => $signers['notedBy'] ?? null,
            'certifiedBy' => $signers['certifiedBy'] ?? null,
        ]);
    }

    public function update(Request $request, AppCsePlan $appCsePlan): RedirectResponse
    {
        $this->scoped($request, $appCsePlan);
        $this->service->updateHeader($appCsePlan, $request->only(self::HEADER));

        return back()->with('success', 'The APP-CSE details were saved.');
    }

    public function storeItem(Request $request, AppCsePlan $appCsePlan): RedirectResponse
    {
        $this->scoped($request, $appCsePlan);
        $data = $request->validate(['cse_catalog_item_id' => ['required', 'integer'], 'unit_price' => ['nullable', 'numeric']]);
        $item = $this->service->addCatalogItem($appCsePlan, (int) $data['cse_catalog_item_id'], isset($data['unit_price']) ? (float) $data['unit_price'] : null);

        return back()->with('success', $item->description.' was added. Type its monthly quantities.');
    }

    public function storeCustomItem(Request $request, AppCsePlan $appCsePlan): RedirectResponse
    {
        $this->scoped($request, $appCsePlan);
        $item = $this->service->addCustomItem($appCsePlan, $request->only(['category', 'description', 'unit', 'unit_price']));

        return back()->with('success', $item->description.' was added to Part II.');
    }

    public function updateItem(Request $request, AppCseItem $appCseItem): RedirectResponse
    {
        $this->scoped($request, $appCseItem->plan()->firstOrFail());
        $data = $request->only([...self::MONTHS, 'category', 'description', 'unit', 'unit_price']);
        foreach (self::MONTHS as $month) {
            // An empty month box means no quantity that month.
            if (array_key_exists($month, $data) && ($data[$month] === null || (is_string($data[$month]) && trim($data[$month]) === ''))) {
                $data[$month] = '0';
            }
        }
        $this->service->updateItem($appCseItem, $data);

        return back()->with('success', 'The item was saved.');
    }

    /** The official APP-CSE drawn from an entry that is not saved yet: the entry is made inside a transaction that is rolled back. An entry that does not pass is left out of the drawing. */
    public function previewPlan(Request $request, AppCsePlan $appCsePlan)
    {
        return $this->previewWith($request, $appCsePlan, fn () => $this->update($request, $appCsePlan));
    }

    public function previewItem(Request $request, AppCsePlan $appCsePlan)
    {
        return $this->previewWith($request, $appCsePlan, fn () => $this->storeItem($request, $appCsePlan));
    }

    public function previewCustomItem(Request $request, AppCsePlan $appCsePlan)
    {
        return $this->previewWith($request, $appCsePlan, fn () => $this->storeCustomItem($request, $appCsePlan));
    }

    public function previewItemUpdate(Request $request, AppCseItem $appCseItem)
    {
        return $this->previewWith($request, $appCseItem->plan()->firstOrFail(), fn () => $this->updateItem($request, $appCseItem));
    }

    private function previewWith(Request $request, AppCsePlan $plan, Closure $apply)
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

    public function destroyItem(Request $request, AppCseItem $appCseItem): RedirectResponse
    {
        $this->scoped($request, $appCseItem->plan()->firstOrFail());
        $this->service->removeItem($appCseItem);

        return back()->with('success', 'The item was removed.');
    }

    public function approve(Request $request, AppCsePlan $appCsePlan): RedirectResponse
    {
        $this->scoped($request, $appCsePlan);
        $this->service->approve($appCsePlan, $request->user());

        return back()->with('success', 'The APP-CSE for FY '.$appCsePlan->fiscal_year.' was approved and is now locked.');
    }

    public function reopen(Request $request, AppCsePlan $appCsePlan): RedirectResponse
    {
        $this->scoped($request, $appCsePlan);
        $this->service->reopen($appCsePlan, $request->user());

        return back()->with('success', 'The APP-CSE is a draft again. Make your changes, then approve it.');
    }

    /** @return array<int, int> the schools this user may work with (the same rule as the Planning page and the SOB) */
    private function schoolIds(Request $request): array
    {
        $user = $request->user();

        return ($user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id'))->all();
    }

    private function scoped(Request $request, AppCsePlan $plan): void
    {
        abort_unless(in_array($plan->school_id, $this->schoolIds($request), true), 403);
    }
}
