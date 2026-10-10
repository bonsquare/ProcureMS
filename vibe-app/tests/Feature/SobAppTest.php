<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\BudgetAllocation;
use App\Models\PpmpPlan;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\SobPlan;
use App\Services\MasterTransactionService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

class SobAppTest extends TestCase
{
    use RefreshDatabase;

    private function line(array $fx, array $override = []): array
    {
        return $override + ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 250];
    }

    /** An approved Q1 SOB with the given item lines. */
    private function approvedSob(array $fx, array $lines, int $quarter = 1): SobPlan
    {
        $service = app(SobService::class);
        $plan = $service->create($fx['school'], 2026, $quarter, 'MOOE', $fx['user']);
        foreach ($lines as $line) {
            $service->addItem($plan, $line);
        }

        return $service->approve($plan->fresh(), $fx['user']) ? $plan->fresh() : $plan;
    }

    private function generate(array $fx)
    {
        return $this->post(route('planning.app.generate'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026]);
    }

    public function test_generate_app_takes_the_items_of_approved_sobs_with_frequency_times_quantity(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approvedSob($fx, [$this->line($fx), $this->line($fx, ['particulars' => 'Caretaker', 'frequency' => 3, 'quantity' => 1, 'unit' => 'month', 'unit_cost' => 3000])]);
        $this->actingAs($fx['user']);

        $this->generate($fx)->assertSessionHasNoErrors()->assertSessionHas('success');

        $items = AppItem::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $paper = $items[0];
        $this->assertSame(['Bond paper', 'Activity 1', 'ream', 'MOOE'], [$paper->procurement_item, $paper->specifications, $paper->unit, $paper->fund_source]);
        $this->assertEquals([10, 250, 2500], [$paper->quantity, $paper->estimated_unit_cost, $paper->estimated_total_cost]);
        $this->assertNull($paper->ppmp_item_id);
        $this->assertSame($plan->items()->first()->id, $paper->sob_item_id);
        $caretaker = $items[1];
        $this->assertEquals([3, 3000, 9000], [$caretaker->quantity, $caretaker->estimated_unit_cost, $caretaker->estimated_total_cost]);
        $this->assertSame('draft', AppPlan::withoutGlobalScopes()->sole()->status);
    }

    public function test_generating_twice_adds_no_duplicates_and_leaves_ppmp_items_alone(): void
    {
        $fx = SobFixture::make();
        $ppmp = PpmpPlan::create(['school_id' => $fx['school']->id, 'aip_id' => $fx['aip']->id, 'master_transaction_id' => app(MasterTransactionService::class)->forAip($fx['aip'], $fx['user'])->id, 'fiscal_year' => 2026, 'project_title' => 'Legacy', 'status' => 'approved', 'created_by' => $fx['user']->id]);
        $ppmpItem = $ppmp->items()->create(['organization_id' => $fx['organization']->id, 'procurement_item' => 'Legacy item', 'quantity' => 2, 'unit' => 'pc', 'estimated_unit_cost' => 10, 'estimated_total_cost' => 20]);
        $app = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => 'draft', 'created_by' => $fx['user']->id]);
        $legacy = AppItem::create(['organization_id' => $fx['organization']->id, 'app_plan_id' => $app->id, 'ppmp_item_id' => $ppmpItem->id, 'procurement_item' => 'Legacy item', 'quantity' => 2, 'unit' => 'pc', 'estimated_unit_cost' => 10, 'estimated_total_cost' => 20]);
        $this->approvedSob($fx, [$this->line($fx)]);
        $this->actingAs($fx['user']);

        $this->generate($fx)->assertSessionHasNoErrors();
        $this->generate($fx)->assertSessionHasNoErrors();

        $this->assertSame(2, AppItem::withoutGlobalScopes()->count());
        $this->assertSame(1, AppItem::withoutGlobalScopes()->whereNotNull('sob_item_id')->count());
        $this->assertEquals(20, $legacy->fresh()->estimated_total_cost);
        $this->assertSame($ppmpItem->id, $legacy->fresh()->ppmp_item_id);
    }

    public function test_draft_sobs_are_not_included_and_none_approved_is_refused(): void
    {
        $fx = SobFixture::make();
        $service = app(SobService::class);
        $draft = $service->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $service->addItem($draft, $this->line($fx));
        $this->actingAs($fx['user']);

        $this->generate($fx)->assertSessionHasErrors('planning');
        $this->assertSame(0, AppItem::withoutGlobalScopes()->count());

        $this->approvedSob($fx, [$this->line($fx, ['particulars' => 'Approved only'])], 2);
        $this->generate($fx)->assertSessionHasNoErrors();
        $this->assertSame(['Approved only'], AppItem::withoutGlobalScopes()->pluck('procurement_item')->all());
    }

    public function test_a_purchase_request_can_draw_a_sob_based_app_item_within_its_quantity_and_cost(): void
    {
        $fx = SobFixture::make();
        // A second, larger line gives the allotment room, so the APP limits (not the budget) are what these requests meet.
        $plan = $this->approvedSob($fx, [$this->line($fx), $this->line($fx, ['particulars' => 'Big item', 'quantity' => 40])]);
        $this->actingAs($fx['user']);
        $this->generate($fx);
        $app = AppPlan::withoutGlobalScopes()->sole();
        $this->post(route('planning.app.approve', $app))->assertSessionHasNoErrors();
        $appItem = AppItem::withoutGlobalScopes()->where('procurement_item', 'Bond paper')->sole();
        $allotment = BudgetAllocation::withoutGlobalScopes()->sole();

        $payload = fn (float $quantity, float $price = 250) => [
            'school_id' => $fx['school']->id, 'purpose' => 'Office supplies', 'entity_name' => 'DepEd', 'department_name' => $fx['school']->name,
            'request_date' => '2026-03-01', 'source_of_fund' => 'MOOE', 'budget_allocation_id' => $allotment->id, 'transaction_description' => null, 'section' => null,
            'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null, 'extra_blank_rows' => 0,
            'items' => [['app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => $quantity, 'unit' => 'ream', 'unit_price' => $price]],
        ];

        $this->post(route('procurement.store'), $payload(6))->assertSessionHasNoErrors();
        $this->post(route('procurement.store'), $payload(5))->assertSessionHasErrors('items');
        $this->post(route('procurement.store'), $payload(4, 300))->assertSessionHasErrors('items');
        $this->post(route('procurement.store'), $payload(4))->assertSessionHasNoErrors();

        $this->assertSame(2, ProcurementRequest::count());
        $this->assertSame(2, ProcurementRequestItem::count());
        $this->assertTrue($plan->fresh()->transaction->events()->where('module', 'app')->where('action', 'pr_linked')->exists());
        $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->assertSee('10 of 10 ream');
    }

    public function test_an_aip_with_an_sob_cannot_be_deleted(): void
    {
        $fx = SobFixture::make();
        $this->approvedSob($fx, [$this->line($fx)]);
        Aip::withoutGlobalScopes()->whereKey($fx['aip']->id)->update(['status' => 'draft']);
        BudgetAllocation::withoutGlobalScopes()->delete();
        $this->actingAs($fx['user']);

        $this->delete(route('aip.destroy', $fx['aip']))->assertSessionHasErrors('aip');
        $this->assertNotNull(Aip::withoutGlobalScopes()->find($fx['aip']->id));
    }

    public function test_approving_the_app_records_events_for_sob_items(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approvedSob($fx, [$this->line($fx)]);
        $this->actingAs($fx['user']);
        $this->generate($fx);

        $this->post(route('planning.app.approve', AppPlan::withoutGlobalScopes()->sole()))->assertSessionHasNoErrors();

        $this->assertTrue($plan->fresh()->transaction->events()->where('module', 'app')->where('action', 'approved')->exists());
        $this->assertTrue($plan->fresh()->transaction->events()->where('module', 'app')->where('action', 'included_in_app')->exists());
    }
}
