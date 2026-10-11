<?php

namespace App\Http\Controllers;

use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\CseCatalogItem;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\AppCseService;
use App\Support\SignatorySlots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The APP-CSE of a school: create it for a fiscal year, add catalog and own items with their monthly quantities, approve. The rules live in AppCseService. */
class AppCseController extends Controller
{
    private const MONTHS = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'];

    private const HEADER = [
        'office_name', 'region', 'address', 'account_code', 'organization_type', 'contact_person', 'contact_position', 'contact_email', 'contact_phone',
        'approved_budget', 'approved_budget_words', 'date_prepared', 'agency_head_name', 'agency_head_position', 'signatories',
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
            'canManage' => $request->user()->hasPermission('planning.manage'),
        ]);
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
