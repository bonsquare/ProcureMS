<?php

namespace Tests\Feature;

use App\Models\AgencySetting;
use App\Models\Aip;
use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\AuditLog;
use App\Models\CseCatalogItem;
use App\Models\FiscalYear;
use App\Models\SchoolStaff;
use App\Services\AppCseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

class AppCseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AppCseService
    {
        return app(AppCseService::class);
    }

    /** A school with an approved AIP for FY 2026; the migration has already loaded the real 2026 catalog. */
    private function fixture(): array
    {
        $fx = SobFixture::make(['slug' => uniqid('cse')]);
        $this->actingAs($fx['user']);

        return $fx;
    }

    private function plan(array $fx, int $year = 2026): AppCsePlan
    {
        return $this->service()->create($fx['school'], $year, $fx['user']);
    }

    private function pricedCatalogItem(): CseCatalogItem
    {
        return CseCatalogItem::where('catalog_year', 2026)->whereNotNull('price')->orderBy('sort_order')->firstOrFail();
    }

    private function pricelessCatalogItem(): CseCatalogItem
    {
        return CseCatalogItem::where('catalog_year', 2026)->whereNull('price')->orderBy('sort_order')->firstOrFail();
    }

    private function months(array $quantities): array
    {
        $months = [];
        foreach ($quantities as $month => $quantity) {
            $months['m'.$month] = $quantity;
        }

        return $months;
    }

    private function customItem(AppCsePlan $plan, float $price = 100.0, string $category = 'Electrical supplies'): AppCseItem
    {
        return $this->service()->addCustomItem($plan, ['category' => $category, 'description' => 'Extension wire', 'unit' => 'roll', 'unit_price' => $price]);
    }

    private function refused(callable $call, string $key = 'planning'): void
    {
        try {
            $call();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    public function test_create_fills_the_header_defaults_and_refuses_a_second_plan(): void
    {
        $fx = $this->fixture();
        $fx['school']->update(['name' => 'Lubas Elementary School', 'region' => 'XII - SOCCSKSARGEN', 'address' => 'SITIO LUBAS, MAGPET', 'school_head' => 'CHIQUETO E. DOMINGO', 'contact_email' => 'head@example.com', 'contact_number' => '09170000000']);
        AgencySetting::create(['organization_id' => $fx['organization']->id, 'department_name' => 'DepEd', 'division_name' => 'Cotabato', 'head_name' => 'ROMELITO G. FLORES, CESO V']);

        $plan = $this->plan($fx);

        $this->assertSame('draft', $plan->status);
        $this->assertSame(2026, $plan->fiscal_year);
        $this->assertSame(2026, $plan->catalog_year);
        $this->assertSame($fx['user']->id, $plan->created_by);
        $this->assertSame('DEPED- COTABATO (LUBAS ELEMENTARY SCHOOL)', $plan->office_name);
        $this->assertSame('XII - SOCCSKSARGEN', $plan->region);
        $this->assertSame('SITIO LUBAS, MAGPET', $plan->address);
        $this->assertSame('National Government Agency (NGA)', $plan->organization_type);
        $this->assertSame('CHIQUETO E. DOMINGO', $plan->contact_person);
        $this->assertSame('SCHOOL HEAD', $plan->contact_position);
        $this->assertSame('head@example.com', $plan->contact_email);
        $this->assertSame('09170000000', $plan->contact_phone);
        $this->assertSame('ROMELITO G. FLORES, CESO V', $plan->agency_head_name);
        $this->assertSame('Head of Office/Agency', $plan->agency_head_position);

        $this->refused(fn () => $this->plan($fx), 'fiscal_year');
    }

    public function test_create_needs_a_loaded_catalog_and_an_open_fiscal_year(): void
    {
        $fx = $this->fixture();
        FiscalYear::create(['organization_id' => $fx['organization']->id, 'year' => 2026, 'status' => 'closed']);
        $this->refused(fn () => $this->plan($fx), 'fiscal_year');

        FiscalYear::where('organization_id', $fx['organization']->id)->delete();
        CseCatalogItem::query()->delete();
        $this->refused(fn () => $this->plan($fx));
        $this->assertSame(0, AppCsePlan::count());
    }

    public function test_create_picks_the_newest_catalog_year_at_or_below_the_fiscal_year(): void
    {
        $fx = $this->fixture();
        foreach ([2024, 2030] as $year) {
            CseCatalogItem::create(['catalog_year' => $year, 'category' => 'Solvents', 'code' => 'C-'.$year, 'description' => 'Alcohol', 'unit' => 'bottle', 'price' => 10, 'sort_order' => 1]);
        }

        $this->assertSame(2024, $this->plan($fx, 2025)->catalog_year);
        $this->assertSame(2026, $this->plan($fx, 2026)->catalog_year);
        $this->assertSame(2030, $this->plan($fx, 2031)->catalog_year);
        $this->assertSame(2030, $this->plan($fx, 2020)->catalog_year, 'no catalog at or below the year: the newest loaded one');
    }

    public function test_a_catalog_item_without_a_price_needs_one_and_a_duplicate_is_refused(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $priceless = $this->pricelessCatalogItem();
        $priced = $this->pricedCatalogItem();

        $this->refused(fn () => $this->service()->addCatalogItem($plan, $priceless->id), 'unit_price');
        $item = $this->service()->addCatalogItem($plan, $priceless->id, 1234.5);
        $this->assertSame('1234.50', (string) $item->unit_price);
        $this->assertSame($priceless->description, $item->description);
        $this->assertSame($priceless->category, $item->category);

        $own = $this->service()->addCatalogItem($plan, $priced->id);
        $this->assertSame((string) $priced->price, (string) $own->unit_price, 'the catalog price is the plan price');
        $this->assertSame($priced->sort_order, $own->sort_order);

        $this->refused(fn () => $this->service()->addCatalogItem($plan, $priced->id), 'cse_catalog_item_id');
        $this->assertSame(2, $plan->items()->count());
    }

    public function test_a_catalog_item_of_another_catalog_year_is_refused(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $other = CseCatalogItem::create(['catalog_year' => 2027, 'category' => 'Solvents', 'code' => 'C-27', 'description' => 'Alcohol', 'unit' => 'bottle', 'price' => 10, 'sort_order' => 1]);

        $this->refused(fn () => $this->service()->addCatalogItem($plan, $other->id), 'cse_catalog_item_id');
        $this->refused(fn () => $this->service()->addCatalogItem($plan, 999999), 'cse_catalog_item_id');
    }

    public function test_custom_items_and_the_editable_fields(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $custom = $this->customItem($plan, 25);
        $priced = $this->service()->addCatalogItem($plan, $this->pricedCatalogItem()->id);
        $priceless = $this->service()->addCatalogItem($plan, $this->pricelessCatalogItem()->id, 50);

        $this->assertTrue($custom->isCustom());
        $this->assertFalse($priced->isCustom());

        $this->service()->updateItem($custom, ['description' => 'Wire 2.0mm', 'unit' => 'box', 'unit_price' => 30, 'category' => 'Hardware', 'm3' => 4]);
        $this->assertSame(['Wire 2.0mm', 'box', '30.00', 'Hardware', '4.00'], [$custom->fresh()->description, $custom->fresh()->unit, (string) $custom->fresh()->unit_price, $custom->fresh()->category, (string) $custom->fresh()->m3]);

        $original = $priced->description;
        $this->service()->updateItem($priced, ['description' => 'Changed', 'unit_price' => 1, 'm1' => 7]);
        $this->assertSame($original, $priced->fresh()->description, 'a catalog item keeps its catalog description');
        $this->assertSame((string) $this->pricedCatalogItem()->price, (string) $priced->fresh()->unit_price, 'and its catalog price');
        $this->assertSame('7.00', (string) $priced->fresh()->m1);

        $this->service()->updateItem($priceless, ['unit_price' => 75]);
        $this->assertSame('75.00', (string) $priceless->fresh()->unit_price, 'a price that the catalog lacks can be typed');

        $this->service()->removeItem($custom);
        $this->assertSame(2, $plan->items()->count());
    }

    public function test_items_compute_quarters_amounts_and_totals(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $item = $this->customItem($plan, 10.5);
        $this->service()->updateItem($item, $this->months([1 => 1, 2 => 2, 3 => 3, 7 => 4, 12 => 5]));
        $item = $item->fresh();

        $this->assertSame([6.0, 0.0, 4.0, 5.0], [$item->quarterQuantity(1), $item->quarterQuantity(2), $item->quarterQuantity(3), $item->quarterQuantity(4)]);
        $this->assertSame([63.0, 0.0, 42.0, 52.5], [$item->quarterAmount(1), $item->quarterAmount(2), $item->quarterAmount(3), $item->quarterAmount(4)]);
        $this->assertSame(15.0, $item->totalQuantity());
        $this->assertSame(157.5, $item->totalAmount());
        $this->assertSame([1.0, 2.0, 3.0, 0.0, 0.0, 0.0, 4.0, 0.0, 0.0, 0.0, 0.0, 5.0], $item->months());
    }

    public function test_totals_follow_the_labels(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $priced = $this->pricedCatalogItem();
        $this->assertSame('139.36', (string) $priced->price, 'the first priced catalog item is the one in the owner file');

        $partOne = $this->service()->addCatalogItem($plan, $priced->id);
        $this->service()->updateItem($partOne, $this->months([1 => 2, 4 => 1]));
        $partTwo = $this->customItem($plan, 100);
        $this->service()->updateItem($partTwo, $this->months([2 => 5, 5 => 2, 8 => 1]));
        $this->service()->updateHeader($plan, ['approved_budget' => 5000]);

        $totals = $this->service()->totals($plan->fresh());

        $this->assertSame(1218.08, $totals['a']);
        $this->assertSame(121.81, $totals['b']);
        $this->assertSame(60.9, $totals['c']);
        $this->assertSame(1400.79, $totals['d']);
        $this->assertSame(5000.0, $totals['e']);
        $this->assertSame(['q1' => 278.72, 'q2' => 139.36, 'q3' => 0.0, 'q4' => 0.0, 'year' => 418.08], $totals['g1']);
        $this->assertSame(['q1' => 500.0, 'q2' => 200.0, 'q3' => 100.0, 'q4' => 0.0, 'year' => 800.0], $totals['g2']);
        $this->assertSame(['q1' => 778.72, 'q2' => 339.36, 'q3' => 100.0, 'q4' => 0.0, 'year' => 1218.08], $totals['f']);
    }

    public function test_an_empty_plan_has_zero_totals_and_no_budget(): void
    {
        $fx = $this->fixture();

        $totals = $this->service()->totals($this->plan($fx));

        $this->assertSame([0.0, 0.0, 0.0, 0.0, null], [$totals['a'], $totals['b'], $totals['c'], $totals['d'], $totals['e']]);
        $this->assertSame(['q1' => 0.0, 'q2' => 0.0, 'q3' => 0.0, 'q4' => 0.0, 'year' => 0.0], $totals['f']);
    }

    public function test_approval_needs_a_quantity(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $this->refused(fn () => $this->service()->approve($plan, $fx['user']));

        $item = $this->customItem($plan);
        $this->refused(fn () => $this->service()->approve($plan, $fx['user']));

        $this->service()->updateItem($item, ['m1' => 1]);
        $approved = $this->service()->approve($plan->fresh(), $fx['user']);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_an_approved_plan_refuses_every_change(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $item = $this->customItem($plan);
        $this->service()->updateItem($item, ['m1' => 3]);
        $plan = $this->service()->approve($plan->fresh(), $fx['user']);
        $staff = SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Pat Staff']);

        $this->refused(fn () => $this->service()->addCatalogItem($plan, $this->pricedCatalogItem()->id));
        $this->refused(fn () => $this->service()->addCustomItem($plan, ['category' => 'x', 'description' => 'y', 'unit' => 'pc', 'unit_price' => 1]));
        $this->refused(fn () => $this->service()->updateItem($item, ['m2' => 1]));
        $this->refused(fn () => $this->service()->removeItem($item));
        $this->refused(fn () => $this->service()->updateHeader($plan, ['office_name' => 'Changed']));
        $this->refused(fn () => $this->service()->updateHeader($plan, ['signatories' => ['prepared_by' => (string) $staff->id]]));
        $this->refused(fn () => $this->service()->approve($plan, $fx['user']));

        $this->assertSame('0.00', (string) $item->fresh()->m2);
        $this->assertNotSame('Changed', $plan->fresh()->office_name);
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_the_signatories_are_validated_and_kept_when_the_key_is_absent(): void
    {
        // The other school is made first, while nobody is signed in: a signed-in school user cannot create another organization's records.
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $stranger = SchoolStaff::create(['organization_id' => $other['organization']->id, 'school_id' => $other['school']->id, 'name' => 'Stranger']);
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $pat = SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Pat Staff', 'position' => 'Supply Officer']);

        $this->refused(fn () => $this->service()->updateHeader($plan, ['signatories' => ['noted_by' => (string) $stranger->id]]), 'signatories.noted_by');

        $this->service()->updateHeader($plan, ['signatories' => ['prepared_by' => (string) $pat->id, 'noted_by' => '']]);
        $this->assertSame(['prepared_by' => ['staff_id' => $pat->id, 'name' => 'Pat Staff', 'position' => 'Supply Officer']], $plan->fresh()->signatories);

        $this->service()->updateHeader($plan->fresh(), ['contact_person' => 'Someone Else']);
        $this->assertNotNull($plan->fresh()->signatories, 'a header save without the signatories key keeps them');
        $this->assertSame('Someone Else', $plan->fresh()->contact_person);

        $this->service()->updateHeader($plan->fresh(), ['signatories' => ['prepared_by' => '']]);
        $this->assertEmpty($plan->fresh()->signatories);
    }

    public function test_the_header_fields_the_form_can_change_are_saved_and_unknown_ones_ignored(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);

        $this->service()->updateHeader($plan, [
            'office_name' => 'DEPED- X', 'account_code' => 'F001', 'approved_budget' => '456708.70', 'approved_budget_words' => 'Four hundred fifty-six thousand',
            'date_prepared' => '2026-10-11', 'agency_head_name' => 'A. Head', 'agency_head_position' => 'SDS', 'status' => 'approved', 'organization_id' => 999,
        ]);

        $fresh = $plan->fresh();
        $this->assertSame(['DEPED- X', 'F001', '456708.70', 'Four hundred fifty-six thousand', '2026-10-11', 'A. Head', 'SDS'], [
            $fresh->office_name, $fresh->account_code, (string) $fresh->approved_budget, $fresh->approved_budget_words, $fresh->date_prepared->toDateString(), $fresh->agency_head_name, $fresh->agency_head_position,
        ]);
        $this->assertSame('draft', $fresh->status, 'status can only change through approve()');
        $this->assertSame($fx['organization']->id, $fresh->organization_id);
    }

    public function test_the_audit_log_and_the_master_transaction_event_are_written(): void
    {
        $fx = $this->fixture();
        $plan = $this->plan($fx);
        $item = $this->customItem($plan);
        $this->service()->updateItem($item, ['m1' => 2]);
        $this->service()->approve($plan->fresh(), $fx['user']);

        foreach (['app_cse_created', 'app_cse_approved'] as $action) {
            $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', $action)->where('auditable_type', AppCsePlan::class)->where('auditable_id', $plan->id)->exists(), $action);
        }
        $events = $fx['aip']->fresh()->transaction->events()->where('module', 'app_cse');
        $this->assertSame(['approved', 'draft_created'], (clone $events)->pluck('action')->sort()->values()->all());
    }

    public function test_a_school_without_an_aip_still_gets_a_plan(): void
    {
        $fx = $this->fixture();
        Aip::withoutGlobalScopes()->delete();

        $plan = $this->plan($fx);

        $this->assertSame('draft', $plan->status);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'app_cse_created')->exists());
    }
}
