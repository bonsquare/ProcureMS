<?php

namespace Tests\Feature;

use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** An approved SOB is edited in place; its Budget allotments and APP lines follow at once. Purchase Requests still protect what they draw from. */
class SobApprovedEditTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(int $quantity = 10): array
    {
        return [['particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => $quantity, 'unit' => 'ream', 'unit_cost' => 100]];
    }

    private function electric(array $fx): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020'], ['title' => 'Electricity Expenses', 'category' => 'Expense']);
    }

    /** An approved Q1 MOOE SOB with Bond paper (10 x 100 = 1,000) under the first activity and, optionally, a second Item on another account. */
    private function approved(array $fx, bool $withSecondItem = false): SobPlan
    {
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $this->lines());
        if ($withSecondItem) {
            $this->service()->addItems($plan, $fx['activities'][0]->id, $this->electric($fx)->id, [['particulars' => 'Bulbs', 'frequency' => 1, 'quantity' => 3, 'unit' => 'pcs', 'unit_cost' => 100]]);
        }
        $this->service()->approve($plan->fresh(), $fx['user']);

        return $plan->fresh();
    }

    private function quarterAmount(string $fund = 'MOOE', int $quarter = 1, ?int $accountId = null): float
    {
        return (float) BudgetAllocation::withoutGlobalScopes()->where('source_of_fund', $fund)->when($accountId, fn ($q) => $q->where('chart_of_account_id', $accountId))->sum('q'.$quarter.'_amount');
    }

    private function appItemFor(array $fx, SobPlan $plan, string $appStatus): AppItem
    {
        $appPlan = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => $appStatus]);

        return AppItem::create([
            'organization_id' => $fx['organization']->id, 'app_plan_id' => $appPlan->id, 'sob_item_id' => $plan->items()->orderBy('id')->first()->id, 'procurement_item' => 'Bond paper',
            'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 1000, 'fund_source' => 'MOOE',
        ]);
    }

    private function fails(callable $action): ValidationException
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e;
        }
        $this->fail('Expected the change to be refused.');
    }

    public function test_editing_an_approved_item_updates_the_budget_allotment_at_once_and_stays_approved(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $this->lines(25), 'extras' => []]);

        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertSame(2500.0, $this->quarterAmount());
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::where('action', 'sob_revised')->exists(), 'the change is in the audit log');
    }

    public function test_adding_an_item_to_an_approved_sob_adds_to_the_allotment(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);
        $electric = $this->electric($fx);

        $this->service()->addItems($plan, $fx['activities'][0]->id, $electric->id, $this->lines(5));

        $this->assertSame(1000.0, $this->quarterAmount('MOOE', 1, $fx['account']->id));
        $this->assertSame(500.0, $this->quarterAmount('MOOE', 1, $electric->id), 'a new budget line for the new account');
    }

    public function test_removing_or_moving_an_item_releases_the_old_allotment(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx, true);
        $electric = $this->electric($fx);

        $this->service()->deleteGroup($plan, $fx['activities'][0]->id, $electric->id);
        $this->assertSame(0.0, $this->quarterAmount('MOOE', 1, $electric->id));
        $this->assertSame(1000.0, $this->quarterAmount('MOOE', 1, $fx['account']->id));

        $this->service()->saveGroup($plan->fresh(), $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $electric->id, 'lines' => $this->lines(), 'extras' => []]);
        $this->assertSame(0.0, $this->quarterAmount('MOOE', 1, $fx['account']->id), 'the amount left the old account');
        $this->assertSame(1000.0, $this->quarterAmount('MOOE', 1, $electric->id), 'and arrived on the new one');
    }

    public function test_the_header_can_move_an_approved_sob_to_another_fund_and_quarter(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $this->service()->updateHeader($plan, ['fund_source' => 'SEF', 'quarter' => 2]);

        $this->assertSame(0.0, $this->quarterAmount('MOOE', 1));
        $this->assertSame(1000.0, $this->quarterAmount('SEF', 2));
        $this->assertSame(['SEF', 2, 'approved'], [$plan->fresh()->fund_source, $plan->fresh()->quarter, $plan->fresh()->status]);
    }

    public function test_deleting_an_approved_sob_releases_its_allotments(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx, true);

        $this->service()->deletePlan($plan);

        $this->assertNull(SobPlan::find($plan->id));
        $this->assertSame(0, SobItem::count());
        $this->assertSame(0.0, $this->quarterAmount());
    }

    public function test_a_change_that_would_leave_less_than_is_obligated_is_refused_and_changes_nothing(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx, true);
        $electric = $this->electric($fx);
        $line = BudgetAllocation::withoutGlobalScopes()->where('chart_of_account_id', $electric->id)->firstOrFail();
        ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-OBL', 'title' => 'Bulbs', 'amount' => 200, 'status' => 'submitted', 'budget_allocation_id' => $line->id]);

        $error = $this->fails(fn () => $this->service()->deleteGroup($plan, $fx['activities'][0]->id, $electric->id));

        $this->assertStringContainsString('obligated', $error->errors()['planning'][0]);
        $this->assertSame(300.0, $this->quarterAmount('MOOE', 1, $electric->id));
        $this->assertSame(2, SobItem::count(), 'the Item is still there');
    }

    public function test_items_in_an_approved_app_ask_for_the_app_to_be_unlocked_first(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);
        $appItem = $this->appItemFor($fx, $plan, 'approved');

        $error = $this->fails(fn () => $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $this->lines(20), 'extras' => []]));

        $this->assertStringContainsString('Unlock the APP', $error->errors()['planning'][0]);
        $this->assertSame(1000.0, $this->quarterAmount());

        $appItem->plan->update(['status' => 'draft']);
        $keepRow = $this->lines(20);
        $keepRow[0]['id'] = $appItem->sob_item_id;
        $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $keepRow, 'extras' => []]);
        $this->assertSame([20.0, 2000.0], [(float) $appItem->fresh()->quantity, (float) $appItem->fresh()->estimated_total_cost], 'the APP line follows the SOB');
    }

    public function test_deleting_an_item_removes_its_line_from_a_draft_app(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx, true);
        $appItem = $this->appItemFor($fx, $plan, 'draft');

        $this->service()->deleteGroup($plan, $fx['activities'][0]->id, $fx['account']->id);

        $this->assertNull(AppItem::find($appItem->id));
    }

    public function test_a_purchase_request_protects_the_items_it_draws_from(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);
        $appItem = $this->appItemFor($fx, $plan, 'draft');
        $request = ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-SOB', 'title' => 'Paper', 'amount' => 500, 'status' => 'submitted']);
        ProcurementRequestItem::create(['organization_id' => $fx['organization']->id, 'procurement_request_id' => $request->id, 'app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => 5, 'unit' => 'ream', 'unit_price' => 100, 'total' => 500]);

        $edit = $this->fails(fn () => $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $this->lines(20), 'extras' => []]));
        $delete = $this->fails(fn () => $this->service()->deletePlan($plan));

        $this->assertStringContainsString('Purchase Request', $edit->errors()['planning'][0]);
        $this->assertStringContainsString('Purchase Request', $delete->errors()['planning'][0]);
        $this->assertSame(1, SobItem::count());
        $this->assertNotNull(SobPlan::find($plan->id));
    }

    public function test_the_approved_page_offers_edit_remove_and_delete_to_managers_only(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-edit-item'));
        $this->assertStringContainsString('Remove item', $html);
        $this->assertStringContainsString('name="school_id_number"', $html, 'the header is editable too');
        $this->assertStringContainsString('Delete SOB', $html);
        $this->assertStringContainsString('Each change is saved at once', $html);

        $this->put(route('planning.sob.groups.update', $plan), ['old_activity' => $fx['activities'][0]->id, 'old_account' => $fx['account']->id, 'aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $this->lines(30), 'extras' => []])->assertSessionHasNoErrors();
        $this->assertSame(3000.0, $this->quarterAmount());

        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);
        $page = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-edit-item', $page);
        $this->assertStringNotContainsString('Delete SOB', $page);
    }

    public function test_the_http_delete_removes_an_approved_sob(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $this->delete(route('planning.sob.destroy', $plan))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(SobPlan::find($plan->id));
        $this->assertSame(0.0, $this->quarterAmount());
    }
}
