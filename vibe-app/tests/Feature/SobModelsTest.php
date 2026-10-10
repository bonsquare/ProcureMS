<?php

namespace Tests\Feature;

use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\SobItem;
use App\Models\SobPlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SobFixture;
use Tests\TestCase;

class SobModelsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $fx, int $quarter = 1, string $fund = 'MOOE'): SobPlan
    {
        return SobPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'aip_id' => $fx['aip']->id, 'fiscal_year' => 2026, 'quarter' => $quarter, 'fund_source' => $fund, 'status' => 'draft', 'created_by' => $fx['user']->id]);
    }

    private function item(array $fx, SobPlan $plan, float $amount): SobItem
    {
        return SobItem::create(['organization_id' => $fx['organization']->id, 'sob_plan_id' => $plan->id, 'aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 1, 'unit' => 'ream', 'unit_cost' => $amount, 'amount' => $amount]);
    }

    public function test_the_tables_exist_with_the_planned_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('sob_plans', ['organization_id', 'school_id', 'aip_id', 'master_transaction_id', 'fiscal_year', 'quarter', 'fund_source', 'status', 'prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position', 'approved_at', 'created_by']));
        $this->assertTrue(Schema::hasColumns('sob_items', ['organization_id', 'sob_plan_id', 'aip_activity_id', 'chart_of_account_id', 'particulars', 'frequency', 'quantity', 'unit', 'unit_cost', 'amount']));
        $this->assertTrue(Schema::hasColumn('app_items', 'sob_item_id'));
    }

    public function test_an_app_item_can_exist_without_a_ppmp_item(): void
    {
        $fx = SobFixture::make();
        $item = $this->item($fx, $this->plan($fx), 100);
        $app = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => 'draft', 'created_by' => $fx['user']->id]);

        $appItem = AppItem::create(['organization_id' => $fx['organization']->id, 'app_plan_id' => $app->id, 'ppmp_item_id' => null, 'sob_item_id' => $item->id, 'procurement_item' => 'Bond paper', 'quantity' => 1, 'unit' => 'ream', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 100]);

        $this->assertSame($item->id, $appItem->fresh()->sobItem->id);
        $this->expectException(QueryException::class);
        AppItem::create(['organization_id' => $fx['organization']->id, 'app_plan_id' => $app->id, 'ppmp_item_id' => null, 'sob_item_id' => $item->id, 'procurement_item' => 'Again', 'quantity' => 1, 'unit' => 'ream', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 100]);
    }

    public function test_one_sob_per_school_year_quarter_and_fund(): void
    {
        $fx = SobFixture::make();
        $this->plan($fx);
        $this->plan($fx, 2);
        $this->plan($fx, 1, 'SEF');

        $this->expectException(QueryException::class);
        $this->plan($fx);
    }

    public function test_deleting_an_sob_deletes_its_items(): void
    {
        $fx = SobFixture::make();
        $plan = $this->plan($fx);
        $this->item($fx, $plan, 50);

        $plan->delete();

        $this->assertSame(0, SobItem::withoutGlobalScopes()->count());
    }

    public function test_total_is_the_sum_of_item_amounts_and_items_load_their_relations(): void
    {
        $fx = SobFixture::make();
        $plan = $this->plan($fx);
        $item = $this->item($fx, $plan, 150.25);
        $this->item($fx, $plan, 49.75);

        $this->assertSame(200.0, $plan->fresh()->total());
        $this->assertSame($plan->id, $item->plan->id);
        $this->assertSame($fx['activities'][0]->id, $item->activity->id);
        $this->assertSame('Office Supplies Expenses', $item->account->title);
        $this->assertSame(2, $plan->items()->count());
    }

    public function test_sob_models_are_scoped_to_the_organization(): void
    {
        $mine = SobFixture::make(['slug' => 'minesob']);
        $theirs = SobFixture::make(['slug' => 'theirsob']);
        $this->plan($theirs);

        $this->actingAs($mine['user']);
        $this->assertSame(0, SobPlan::count());
        $this->assertSame(1, SobPlan::withoutGlobalScopes()->count());
    }

    public function test_an_activity_used_by_an_sob_line_cannot_be_deleted_at_the_database_level(): void
    {
        $fx = SobFixture::make();
        $this->item($fx, $this->plan($fx), 100);

        $this->expectException(QueryException::class);
        $fx['activities'][0]->delete();
    }
}
