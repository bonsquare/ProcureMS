<?php

namespace App\Services;

use App\Models\AgencySetting;
use App\Models\Aip;
use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\AuditLog;
use App\Models\CseCatalogItem;
use App\Models\School;
use App\Models\SobItem;
use App\Models\User;
use App\Support\AppCategories;
use App\Support\SignatorySlots;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Every rule and every calculation of the APP-CSE (the Procurement Service's Annual Procurement Plan for Common Supplies and Equipment).
 * A plan is a draft until it is approved; an approved plan is locked. The totals are always computed from the items.
 */
class AppCseService
{
    /** Line B of the form: additional provision for inflation, 10% of the total. */
    public const INFLATION_RATE = 0.10;

    /** Line C of the form: additional provision for transport and freight cost, 5% of the total. */
    public const FREIGHT_RATE = 0.05;

    private const MONTH_RULES = ['numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];

    private const PRICE_RULES = ['numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];

    public function __construct(private FiscalYearService $fiscalYears, private MasterTransactionService $transactions) {}

    public function create(School $school, int $fiscalYear, User $user): AppCsePlan
    {
        $this->fiscalYears->assertOpen((int) $school->organization_id, $fiscalYear);
        if (AppCsePlan::withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->exists()) {
            $this->fail('This school already has an APP-CSE for FY '.$fiscalYear.'.', 'fiscal_year');
        }
        $catalogYear = CseCatalogItem::where('catalog_year', '<=', $fiscalYear)->max('catalog_year') ?? CseCatalogItem::max('catalog_year');
        if ($catalogYear === null) {
            $this->fail('No Procurement Service catalog is loaded. Load one with php artisan cse:import-catalog first.');
        }
        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first();

        return DB::transaction(function () use ($school, $fiscalYear, $catalogYear, $agency, $user) {
            $division = trim((string) $agency?->division_name);
            $office = trim(trim((string) ($agency?->department_name ?: 'DEPED')).($division !== '' ? '- '.$division : ''));

            $plan = AppCsePlan::create([
                'organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => $fiscalYear, 'catalog_year' => (int) $catalogYear,
                'status' => 'draft', 'created_by' => $user->id,
                'office_name' => strtoupper($office.' ('.$school->name.')'), 'region' => $school->region, 'address' => $school->address,
                'organization_type' => 'National Government Agency (NGA)',
                'contact_person' => $school->school_head, 'contact_position' => $school->school_head ? 'SCHOOL HEAD' : null,
                'contact_email' => $school->contact_email, 'contact_phone' => $school->contact_number,
                'agency_head_name' => $agency?->head_name, 'agency_head_position' => 'Head of Office/Agency',
            ]);
            $this->audit($plan, 'app_cse_created');
            $this->event($plan, $user, 'draft_created', null, 'draft');

            return $plan;
        });
    }

    public function addCatalogItem(AppCsePlan $plan, int $catalogItemId, ?float $price = null): AppCseItem
    {
        $this->requireDraft($plan);
        $catalog = CseCatalogItem::find($catalogItemId);
        if (! $catalog || (int) $catalog->catalog_year !== (int) $plan->catalog_year) {
            $this->fail('That item is not in the '.$plan->catalog_year.' catalog.', 'cse_catalog_item_id');
        }
        if ($plan->items()->where('cse_catalog_item_id', $catalog->id)->exists()) {
            $this->fail('That catalog item is already in this plan.', 'cse_catalog_item_id');
        }
        $unitPrice = $catalog->price !== null ? (float) $catalog->price : $price;
        if ($unitPrice === null || $unitPrice <= 0) {
            $this->fail('This catalog item has no price. Type its price.', 'unit_price');
        }
        $this->validate(['unit_price' => $unitPrice], ['unit_price' => ['required', ...self::PRICE_RULES]]);

        $item = AppCseItem::create([
            'organization_id' => $plan->organization_id, 'app_cse_plan_id' => $plan->id, 'cse_catalog_item_id' => $catalog->id, 'category' => $catalog->category,
            'description' => $catalog->description, 'unit' => $catalog->unit, 'unit_price' => $unitPrice, 'sort_order' => $catalog->sort_order,
        ]);
        $this->audit($plan, 'app_cse_item_added', ['item' => $item->description]);

        return $item;
    }

    /** @param  array<string, mixed>  $data  category, description, unit and unit_price of a Part II item */
    public function addCustomItem(AppCsePlan $plan, array $data): AppCseItem
    {
        $this->requireDraft($plan);
        $values = $this->validate($data, [
            'category' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:500'], 'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', ...self::PRICE_RULES],
        ]);

        $item = AppCseItem::create([
            'organization_id' => $plan->organization_id, 'app_cse_plan_id' => $plan->id, 'cse_catalog_item_id' => null,
            'category' => trim($values['category']), 'description' => trim($values['description']), 'unit' => trim($values['unit']), 'unit_price' => $values['unit_price'],
            'sort_order' => 1 + (int) $plan->items()->whereNull('cse_catalog_item_id')->max('sort_order'),
        ]);
        $this->audit($plan, 'app_cse_item_added', ['item' => $item->description]);

        return $item;
    }

    /**
     * The particulars of the school's approved SOBs, of every category (Common Office Supplies, Drugs and Medicines, Construction Components and Supplies,
     * Paper Materials and Products, Common ICT Equipment, Consumables, Other Categories; not a line left at "-"), written into the APP-CSE of the same fiscal year as Part II lines under their
     * category: the quantity (frequency x quantity) goes in the first month of the SOB's quarter, one line per category, particulars, unit and unit cost.
     * The lines are written again on every change of an SOB, so they follow it; an approved APP-CSE is locked and left as it is.
     * An APP-CSE is created for the year when there is none yet.
     */
    public function syncFromSob(School $school, int $fiscalYear, ?User $user): void
    {
        $items = SobItem::withoutGlobalScopes()->with('plan')->where('is_extra', false)->whereNotNull('category')->where('category', '!=', '')
            ->whereHas('plan', fn ($query) => $query->withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->where('status', 'approved'))
            ->orderBy('id')->get()
            ->sortBy(fn (SobItem $item) => (array_search(AppCategories::of($item->category), AppCategories::ALL, true) * 1000000) + $item->id)->values();
        $plan = AppCsePlan::withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->first();
        if (! $plan) {
            if ($items->isEmpty() || ! $user) {
                return;
            }
            try {
                $plan = $this->create($school, $fiscalYear, $user);
            } catch (ValidationException) {
                return; // a closed fiscal year or no catalog: the SOB is saved, the APP-CSE waits
            }
        }

        $lines = [];
        foreach ($items as $item) {
            $category = AppCategories::of($item->category);
            $key = $category.'|'.mb_strtolower(trim((string) $item->particulars)).'|'.mb_strtolower(trim((string) $item->unit)).'|'.number_format((float) $item->unit_cost, 2, '.', '');
            $month = 'm'.((((int) $item->plan->quarter) - 1) * 3 + 1);
            $lines[$key] ??= ['category' => $category, 'description' => trim((string) $item->particulars), 'unit' => trim((string) $item->unit), 'unit_price' => (float) $item->unit_cost, 'months' => []];
            $lines[$key]['months'][$month] = ($lines[$key]['months'][$month] ?? 0) + (float) $item->frequency * (float) $item->quantity;
        }

        // An approved APP-CSE is left alone while the SOB lines are the same; when they are not (an item came or changed), it goes back to a draft so it follows the SOB.
        if ($plan->isApproved()) {
            $written = AppCseItem::withoutGlobalScopes()->where('app_cse_plan_id', $plan->id)->where('from_sob', true)->get()
                ->map(fn (AppCseItem $item) => $item->category.'|'.mb_strtolower($item->description).'|'.mb_strtolower($item->unit).'|'.number_format((float) $item->unit_price, 2, '.', '').'|'.collect(range(1, 12))->map(fn ($month) => number_format((float) $item->{'m'.$month}, 2, '.', ''))->implode(','))
                ->sort()->values()->all();
            $wanted = collect($lines)->map(fn (array $line) => $line['category'].'|'.mb_strtolower($line['description']).'|'.mb_strtolower($line['unit']).'|'.number_format($line['unit_price'], 2, '.', '').'|'.collect(range(1, 12))->map(fn ($month) => number_format((float) ($line['months']['m'.$month] ?? 0), 2, '.', ''))->implode(','))
                ->sort()->values()->all();
            if ($written === $wanted) {
                return;
            }
            $plan->update(['status' => 'draft', 'approved_at' => null]);
            $this->audit($plan, 'app_cse_reopened_by_sob');
        }

        DB::transaction(function () use ($plan, $lines) {
            AppCseItem::withoutGlobalScopes()->where('app_cse_plan_id', $plan->id)->where('from_sob', true)->delete();
            $order = (int) AppCseItem::withoutGlobalScopes()->where('app_cse_plan_id', $plan->id)->whereNull('cse_catalog_item_id')->max('sort_order');
            foreach ($lines as $line) {
                AppCseItem::withoutGlobalScopes()->create(array_map(fn ($quantity) => round($quantity, 2), $line['months']) + array_fill_keys(['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'], 0) + [
                    'organization_id' => $plan->organization_id, 'app_cse_plan_id' => $plan->id, 'cse_catalog_item_id' => null, 'category' => $line['category'],
                    'description' => $line['description'], 'unit' => $line['unit'], 'unit_price' => $line['unit_price'], 'sort_order' => ++$order, 'from_sob' => true,
                ]);
            }
        });
    }

    /** @param  array<string, mixed>  $data  m1..m12; for a Part II item also category, description, unit and unit_price; for a catalog item unit_price only when the catalog has none */
    public function updateItem(AppCseItem $item, array $data): AppCseItem
    {
        $plan = $item->plan()->firstOrFail();
        $this->requireDraft($plan);
        $this->requireOwnLine($item);

        $rules = [];
        foreach (range(1, 12) as $month) {
            $rules['m'.$month] = ['required', ...self::MONTH_RULES];
        }
        if ($item->isCustom()) {
            $rules += ['category' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:500'], 'unit' => ['required', 'string', 'max:50'], 'unit_price' => ['required', ...self::PRICE_RULES]];
        } elseif ($item->catalogItem?->price === null) {
            $rules['unit_price'] = ['required', ...self::PRICE_RULES];
        }

        $values = $this->validate(array_intersect_key($data, $rules), array_intersect_key($rules, $data));
        foreach (['category', 'description', 'unit'] as $text) {
            if (isset($values[$text])) {
                $values[$text] = trim($values[$text]);
            }
        }
        $item->update($values);
        $this->audit($plan, 'app_cse_item_updated', ['item' => $item->description]);

        return $item->fresh();
    }

    public function removeItem(AppCseItem $item): void
    {
        $plan = $item->plan()->firstOrFail();
        $this->requireDraft($plan);
        $this->requireOwnLine($item);
        $description = $item->description;
        $item->delete();
        $this->audit($plan, 'app_cse_item_removed', ['item' => $description]);
    }

    /** @param  array<string, mixed>  $data  header fields, approved_budget(+_words), date_prepared, agency_head_*, and signatories (replaced when the key is present, kept when absent) */
    public function updateHeader(AppCsePlan $plan, array $data): AppCsePlan
    {
        $this->requireDraft($plan);
        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];
        $rules = [
            'office_name' => $text(255), 'region' => $text(255), 'address' => $text(500), 'account_code' => $text(255), 'organization_type' => $text(255),
            'contact_person' => $text(255), 'contact_position' => $text(255), 'contact_email' => $text(255), 'contact_phone' => $text(255),
            'approved_budget_words' => $text(500), 'agency_head_name' => $text(255), 'agency_head_position' => $text(255), 'agency_head_role' => $text(255),
            'prepared_by_role' => $text(255), 'prepared_by_name' => $text(255), 'prepared_by_position' => $text(255),
            'noted_by_role' => $text(255), 'noted_by_name' => $text(255), 'noted_by_position' => $text(255),
            'certified_by_role' => $text(255), 'certified_by_name' => $text(255), 'certified_by_position' => $text(255),
            'approved_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'], 'date_prepared' => ['nullable', 'date'],
        ];
        $values = $this->validate(array_intersect_key($data, $rules), array_intersect_key($rules, $data));
        foreach ($values as $field => $value) {
            $values[$field] = is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
        }
        if (array_key_exists('signatories', $data)) {
            $values['signatories'] = SignatorySlots::validated('app_cse', $data['signatories'], (int) $plan->school_id) ?: null;
        }

        $plan->update($values);
        $this->audit($plan, 'app_cse_header_updated', ['fields' => array_keys($values)]);

        return $plan->fresh();
    }

    /** An approved APP-CSE goes back to a draft so its details and items can be changed; it is approved again afterwards. */
    public function reopen(AppCsePlan $plan, User $user): AppCsePlan
    {
        if (! $plan->isApproved()) {
            $this->fail('Only an approved APP-CSE can be reopened.');
        }
        $this->fiscalYears->assertOpen((int) $plan->organization_id, (int) $plan->fiscal_year);

        return DB::transaction(function () use ($plan, $user) {
            $plan->update(['status' => 'draft', 'approved_at' => null]);
            $this->audit($plan, 'app_cse_reopened');
            $this->event($plan, $user, 'reopened', 'approved', 'draft');

            return $plan->fresh();
        });
    }

    public function approve(AppCsePlan $plan, User $user): AppCsePlan
    {
        $this->requireDraft($plan);
        if (! $plan->items()->get()->contains(fn (AppCseItem $item) => $item->totalQuantity() > 0)) {
            $this->fail('Enter a quantity for at least one item before approving the APP-CSE.');
        }

        return DB::transaction(function () use ($plan, $user) {
            $plan->update(['status' => 'approved', 'approved_at' => now()]);
            $this->audit($plan, 'app_cse_approved');
            $this->event($plan, $user, 'approved', 'draft', 'approved');

            return $plan->fresh();
        });
    }

    /**
     * The totals block of the form, from the items. A is the sum of every item's total amount, B is 10% of A, C is 5% of A, D = A + B + C,
     * E is the typed approved budget. G.1 and G.2 are the quarter amounts of Part I (catalog) and Part II (own items); F is G.1 + G.2.
     *
     * @return array{a: float, b: float, c: float, d: float, e: ?float, g1: array<string, float>, g2: array<string, float>, f: array<string, float>}
     */
    public function totals(AppCsePlan $plan): array
    {
        $items = $plan->items()->get();
        $a = round($items->sum(fn (AppCseItem $item) => $item->totalAmount()), 2);
        $b = round($a * self::INFLATION_RATE, 2);
        $c = round($a * self::FREIGHT_RATE, 2);
        $quarters = fn ($group) => $this->quarterRow(fn (int $quarter) => $group->sum(fn (AppCseItem $item) => $item->quarterAmount($quarter)));
        $g1 = $quarters($items->reject(fn (AppCseItem $item) => $item->isCustom()));
        $g2 = $quarters($items->filter(fn (AppCseItem $item) => $item->isCustom()));

        return [
            'a' => $a, 'b' => $b, 'c' => $c, 'd' => round($a + $b + $c, 2), 'e' => $plan->approved_budget !== null ? (float) $plan->approved_budget : null,
            'g1' => $g1, 'g2' => $g2, 'f' => $this->quarterRow(fn (int $quarter) => $g1['q'.$quarter] + $g2['q'.$quarter]),
        ];
    }

    /** @return array{q1: float, q2: float, q3: float, q4: float, year: float} */
    private function quarterRow(callable $amount): array
    {
        $row = [];
        foreach (range(1, 4) as $quarter) {
            $row['q'.$quarter] = round((float) $amount($quarter), 2);
        }
        $row['year'] = round($row['q1'] + $row['q2'] + $row['q3'] + $row['q4'], 2);

        return $row;
    }

    private function requireDraft(AppCsePlan $plan): void
    {
        if ($plan->isApproved()) {
            $this->fail('An approved APP-CSE is locked.');
        }
        $this->fiscalYears->assertOpen((int) $plan->organization_id, (int) $plan->fiscal_year);
    }

    /** The plan's fiscal year has an AIP: the plan's creation and approval are events on that year's master transaction. */
    private function event(AppCsePlan $plan, User $user, string $action, ?string $from, string $to): void
    {
        $aip = Aip::withoutGlobalScopes()->where('school_id', $plan->school_id)->where('fiscal_year', $plan->fiscal_year)->first();
        $aip && $this->transactions->forAip($aip, $user)->recordEvent('app_cse', $action, $from, $to, 'APP-CSE FY '.$plan->fiscal_year, ['app_cse_plan_id' => $plan->id]);
    }

    /** @param  array<string, mixed>  $metadata */
    private function audit(AppCsePlan $plan, string $action, array $metadata = []): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'school_id' => $plan->school_id, 'action' => $action, 'auditable_type' => AppCsePlan::class, 'auditable_id' => $plan->id, 'metadata' => $metadata ?: null]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    /** A line written from the SOB is changed in the SOB, so it keeps following it. */
    private function requireOwnLine(AppCseItem $item): void
    {
        if ($item->from_sob) {
            $this->fail('This line comes from the SOB. Change it in the SOB.');
        }
    }

    private function fail(string $message, string $key = 'planning'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
