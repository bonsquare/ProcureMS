<?php

namespace Tests\Feature;

use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\CseCatalogItem;
use App\Models\FiscalYear;
use App\Models\SchoolStaff;
use App\Models\User;
use App\Services\AppCseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SobFixture;
use Tests\TestCase;

class AppCseEntryTest extends TestCase
{
    use RefreshDatabase;

    /** A school user ready to work, signed in. Another school (never signed in as) is made first so its records exist for the isolation tests. */
    private function context(): array
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherPlan = app(AppCseService::class)->create($other['school'], 2026, $other['user']);
        $otherItem = app(AppCseService::class)->addCustomItem($otherPlan, ['category' => 'Other', 'description' => 'Other', 'unit' => 'pc', 'unit_price' => 1]);
        $fx = SobFixture::make(['slug' => uniqid('cse')]);
        $this->actingAs($fx['user']);

        return [$fx, $otherPlan, $otherItem];
    }

    private function plan(array $fx): AppCsePlan
    {
        return app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);
    }

    private function priced(): CseCatalogItem
    {
        return CseCatalogItem::where('catalog_year', 2026)->whereNotNull('price')->orderBy('sort_order')->firstOrFail();
    }

    private function priceless(): CseCatalogItem
    {
        return CseCatalogItem::where('catalog_year', 2026)->whereNull('price')->orderBy('sort_order')->firstOrFail();
    }

    private function custom(AppCsePlan $plan, array $extra = []): AppCseItem
    {
        return app(AppCseService::class)->addCustomItem($plan, $extra + ['category' => 'Hardware', 'description' => 'Tie wire', 'unit' => 'kg', 'unit_price' => 80]);
    }

    public function test_a_user_creates_a_plan_from_the_planning_page_and_it_is_listed(): void
    {
        [$fx] = $this->context();

        $page = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk();
        $page->assertSee('id="app-cse-create"', false);

        $this->post(route('planning.app-cse.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026])->assertSessionHasNoErrors();
        $plan = AppCsePlan::where('school_id', $fx['school']->id)->firstOrFail();

        $this->assertSame(2026, $plan->fiscal_year);
        $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()
            ->assertSee(route('planning.app-cse.show', $plan), false)->assertSee('APP-CSE');
    }

    public function test_one_plan_per_school_and_year_and_the_year_is_checked(): void
    {
        [$fx] = $this->context();
        $payload = ['school_id' => $fx['school']->id, 'fiscal_year' => 2026];

        $this->post(route('planning.app-cse.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('planning.app-cse.store'), $payload)->assertSessionHasErrors('fiscal_year');
        $this->post(route('planning.app-cse.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 1999])->assertSessionHasErrors('fiscal_year');
        $this->post(route('planning.app-cse.store'), ['school_id' => 999999, 'fiscal_year' => 2027])->assertSessionHasErrors('school_id');
        $this->assertSame(1, AppCsePlan::where('school_id', $fx['school']->id)->count());
    }

    public function test_a_catalog_item_is_added_and_a_priceless_item_needs_a_price(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $priced = $this->priced();
        $priceless = $this->priceless();

        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $priced->id])->assertSessionHasNoErrors();
        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee($priced->code)->assertSee('name="m1"', false);
        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $priced->id])->assertSessionHasErrors('cse_catalog_item_id');

        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $priceless->id])->assertSessionHasErrors('unit_price');
        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $priceless->id, 'unit_price' => '1250.50'])->assertSessionHasNoErrors();
        $this->assertSame(2, $plan->items()->count());
    }

    public function test_a_custom_part_ii_item_is_added_and_its_values_are_escaped(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);

        $this->post(route('planning.app-cse.custom.store', $plan), ['category' => '<b>Cat</b>', 'description' => '"Quoted" <i>wire</i>', 'unit' => 'm', 'unit_price' => '12.5'])->assertSessionHasNoErrors();

        $html = $this->get(route('planning.app-cse.show', $plan))->assertOk()->getContent();
        $this->assertStringContainsString('&lt;b&gt;Cat&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Cat</b>', $html);
        $this->assertStringNotContainsString('<i>wire</i>', $html);
        $this->post(route('planning.app-cse.custom.store', $plan), ['category' => '', 'description' => 'x', 'unit' => 'pc', 'unit_price' => '1'])->assertSessionHasErrors('category');
        $this->post(route('planning.app-cse.custom.store', $plan), ['category' => 'c', 'description' => 'x', 'unit' => 'pc', 'unit_price' => 'free'])->assertSessionHasErrors('unit_price');
    }

    /** @return array<string, array{0: mixed}> */
    public static function badMonths(): array
    {
        return ['negative' => ['-1'], 'three decimals' => ['1.234'], 'text' => ['abc'], 'huge' => ['10000000'], 'array' => [['1']], 'exponent' => ['1e3']];
    }

    #[DataProvider('badMonths')]
    public function test_months_are_validated_without_a_server_error(mixed $value): void
    {
        [$fx] = $this->context();
        $item = $this->custom($this->plan($fx));

        $this->put(route('planning.app-cse.items.update', $item), ['m1' => $value])->assertSessionHasErrors('m1');

        $this->assertSame('0.00', (string) $item->fresh()->m1);
    }

    public function test_good_months_are_saved_and_an_item_can_be_removed(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->custom($plan);

        $this->put(route('planning.app-cse.items.update', $item), ['m1' => '12.5', 'm2' => '0', 'm12' => '9999999.99', 'description' => 'Tie wire 1.5mm'])->assertSessionHasNoErrors();
        $fresh = $item->fresh();
        $this->assertSame(['12.50', '0.00', '9999999.99', 'Tie wire 1.5mm'], [(string) $fresh->m1, (string) $fresh->m2, (string) $fresh->m12, $fresh->description]);

        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee('Tie wire 1.5mm');
        $this->delete(route('planning.app-cse.items.destroy', $item))->assertSessionHasNoErrors();
        $this->assertSame(0, $plan->items()->count());
    }

    public function test_the_header_and_signatories_are_saved_and_another_schools_staff_refused(): void
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $stranger = SchoolStaff::create(['organization_id' => $other['organization']->id, 'school_id' => $other['school']->id, 'name' => 'Stranger']);
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $pat = SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Pat Supply', 'position' => 'Property/Supply Officer']);

        $this->get(route('planning.app-cse.show', $plan))->assertOk()
            ->assertSee('name="signatories[prepared_by]"', false)->assertSee('name="agency_head_name"', false)->assertSee('name="approved_budget"', false);

        $this->put(route('planning.app-cse.update', $plan), [
            'office_name' => 'DEPED- X (SCHOOL)', 'account_code' => 'F001', 'approved_budget' => '1000.50', 'approved_budget_words' => 'One thousand pesos and fifty centavos',
            'date_prepared' => '2026-10-11', 'agency_head_name' => 'A. Head', 'agency_head_position' => 'SDS', 'signatories' => ['prepared_by' => (string) $pat->id],
        ])->assertSessionHasNoErrors();
        $fresh = $plan->fresh();
        $this->assertSame(['DEPED- X (SCHOOL)', 'F001', '1000.50', 'A. Head'], [$fresh->office_name, $fresh->account_code, (string) $fresh->approved_budget, $fresh->agency_head_name]);
        $this->assertSame($pat->id, $fresh->signatories['prepared_by']['staff_id']);

        $this->put(route('planning.app-cse.update', $plan), ['signatories' => ['noted_by' => (string) $stranger->id]])->assertSessionHasErrors('signatories.noted_by');
        $this->put(route('planning.app-cse.update', $plan), ['approved_budget' => '-5'])->assertSessionHasErrors('approved_budget');
    }

    public function test_approval_needs_a_quantity_then_locks_the_plan_and_hides_the_edit_controls(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->custom($plan);

        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee('name="m1"', false);
        $this->post(route('planning.app-cse.approve', $plan))->assertSessionHasErrors('planning');
        $this->assertSame('draft', $plan->fresh()->status);

        $this->put(route('planning.app-cse.items.update', $item), ['m1' => '3']);
        $this->post(route('planning.app-cse.approve', $plan))->assertSessionHasNoErrors();
        $this->assertSame('approved', $plan->fresh()->status);

        $page = $this->get(route('planning.app-cse.show', $plan))->assertOk();
        $page->assertDontSee('name="m1"', false)->assertDontSee('name="office_name"', false)->assertSee('Approved');
        $this->put(route('planning.app-cse.items.update', $item), ['m1' => '9'])->assertSessionHasErrors('planning');
        $this->delete(route('planning.app-cse.items.destroy', $item))->assertSessionHasErrors('planning');
        $this->assertSame('3.00', (string) $item->fresh()->m1);
    }

    public function test_an_approved_plan_can_be_reopened_as_a_draft_to_edit_its_details(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->custom($plan);

        $this->post(route('planning.app-cse.reopen', $plan))->assertSessionHasErrors('planning');
        $this->put(route('planning.app-cse.items.update', $item), ['m1' => '3']);
        $this->post(route('planning.app-cse.approve', $plan))->assertSessionHasNoErrors();

        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee(route('planning.app-cse.reopen', $plan), false)->assertSee('Reopen as a draft');
        $this->post(route('planning.app-cse.reopen', $plan))->assertSessionHasNoErrors();
        $this->assertSame('draft', $plan->fresh()->status);
        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee('name="office_name"', false)->assertDontSee('Reopen as a draft');
        $this->put(route('planning.app-cse.update', $plan), ['office_name' => 'CHANGED OFFICE'])->assertSessionHasNoErrors();
        $this->assertSame('CHANGED OFFICE', $plan->fresh()->office_name);
    }

    public function test_a_viewer_can_open_the_plan_but_not_change_it(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->custom($plan);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);

        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertDontSee('name="m1"', false)->assertDontSee('name="office_name"', false);
        $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->assertDontSee('id="app-cse-create"', false);

        $this->post(route('planning.app-cse.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2027])->assertForbidden();
        $this->put(route('planning.app-cse.update', $plan), ['office_name' => 'x'])->assertForbidden();
        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $this->priced()->id])->assertForbidden();
        $this->post(route('planning.app-cse.custom.store', $plan), ['category' => 'c', 'description' => 'd', 'unit' => 'u', 'unit_price' => 1])->assertForbidden();
        $this->put(route('planning.app-cse.items.update', $item), ['m1' => 1])->assertForbidden();
        $this->delete(route('planning.app-cse.items.destroy', $item))->assertForbidden();
        $this->post(route('planning.app-cse.approve', $plan))->assertForbidden();
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_another_organizations_plan_is_refused_everywhere(): void
    {
        [, $otherPlan, $otherItem] = $this->context();
        $this->assertTrue(AppCsePlan::withoutGlobalScopes()->whereKey($otherPlan->id)->exists());

        $statuses = [
            $this->get(route('planning.app-cse.show', $otherPlan))->getStatusCode(),
            $this->put(route('planning.app-cse.update', $otherPlan), ['office_name' => 'x'])->getStatusCode(),
            $this->post(route('planning.app-cse.items.store', $otherPlan), ['cse_catalog_item_id' => $this->priced()->id])->getStatusCode(),
            $this->post(route('planning.app-cse.custom.store', $otherPlan), ['category' => 'c', 'description' => 'd', 'unit' => 'u', 'unit_price' => 1])->getStatusCode(),
            $this->put(route('planning.app-cse.items.update', $otherItem), ['m1' => 5])->getStatusCode(),
            $this->delete(route('planning.app-cse.items.destroy', $otherItem))->getStatusCode(),
            $this->post(route('planning.app-cse.approve', $otherPlan))->getStatusCode(),
            $this->post(route('planning.app-cse.store'), ['school_id' => $otherPlan->school_id, 'fiscal_year' => 2027])->getStatusCode(),
        ];

        foreach ($statuses as $status) {
            $this->assertContains($status, [302, 403, 404]);
        }
        $this->assertSame(array_fill(0, 7, true), array_map(fn ($status) => in_array($status, [403, 404], true), array_slice($statuses, 0, 7)));
        $this->assertSame('0.00', (string) AppCseItem::withoutGlobalScopes()->find($otherItem->id)->m1);
        $this->assertSame('draft', AppCsePlan::withoutGlobalScopes()->find($otherPlan->id)->status);
        $this->assertSame(0, AppCsePlan::withoutGlobalScopes()->where('fiscal_year', 2027)->count());
    }

    public function test_a_closed_fiscal_year_refuses_changes(): void
    {
        [$fx] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->custom($plan);
        FiscalYear::create(['organization_id' => $fx['organization']->id, 'year' => 2026, 'status' => 'closed']);

        $this->put(route('planning.app-cse.items.update', $item), ['m1' => 4])->assertSessionHasErrors('fiscal_year');
        $this->post(route('planning.app-cse.items.store', $plan), ['cse_catalog_item_id' => $this->priced()->id])->assertSessionHasErrors('fiscal_year');
        $this->put(route('planning.app-cse.update', $plan), ['office_name' => 'x'])->assertSessionHasErrors('fiscal_year');
        $this->assertSame('0.00', (string) $item->fresh()->m1);
    }

    public function test_part_i_catalog_search_finds_by_typed_words_and_skips_items_already_added(): void
    {
        [$fx, $otherPlan] = $this->context();
        $plan = $this->plan($fx);
        $item = $this->priced();
        $word = strtolower(explode(' ', trim(preg_replace('/[^A-Za-z ]/', ' ', $item->description)))[0]);

        $found = $this->getJson(route('planning.app-cse.catalog', ['appCsePlan' => $plan, 'q' => $word]))->assertOk()->json();
        $this->assertContains($item->id, array_column($found, 'id'));
        $this->assertLessThanOrEqual(25, count($found));

        $this->getJson(route('planning.app-cse.catalog', ['appCsePlan' => $plan, 'q' => 'x']))->assertOk()->assertExactJson([]);

        app(AppCseService::class)->addCatalogItem($plan, $item->id);
        $after = $this->getJson(route('planning.app-cse.catalog', ['appCsePlan' => $plan, 'q' => $item->code]))->assertOk()->json();
        $this->assertNotContains($item->id, array_column($after, 'id'));

        $this->getJson(route('planning.app-cse.catalog', ['appCsePlan' => $otherPlan, 'q' => $word]))->assertNotFound();
        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee('data-cse-search', false);
        $this->get(route('planning.app-cse.show', $plan))->assertSee(route('planning.app-cse.custom.store', $plan), false)->assertSee('Add my own item to Part II');
    }
}
