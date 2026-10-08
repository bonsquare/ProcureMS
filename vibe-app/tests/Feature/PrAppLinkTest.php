<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\Organization;
use App\Models\PpmpPlan;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MasterTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrAppLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User, 2: AppItem} */
    private function tenantWithApprovedApp(string $slug, string $appStatus = 'approved'): array
    {
        auth()->logout(); // set up each tenant as a system actor, not as the previous tenant's user
        $organization = Organization::create(['name' => strtoupper($slug), 'slug' => $slug]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => "School $slug"]);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $this->actingAs($user);

        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'status' => 'approved']);
        $ppmp = PpmpPlan::create(['school_id' => $school->id, 'aip_id' => $aip->id, 'master_transaction_id' => app(MasterTransactionService::class)->forAip($aip, $user)->id, 'fiscal_year' => 2026, 'project_title' => 'Supplies', 'status' => 'approved']);
        $ppmpItem = $ppmp->items()->create([
            'organization_id' => $organization->id, 'procurement_item' => 'Bond paper', 'quantity' => 10, 'unit' => 'ream',
            'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500,
        ]);
        $app = AppPlan::create(['school_id' => $school->id, 'fiscal_year' => 2026, 'status' => $appStatus]);
        $appItem = AppItem::create([
            'organization_id' => $organization->id, 'app_plan_id' => $app->id, 'ppmp_item_id' => $ppmpItem->id,
            'procurement_item' => 'Bond paper', 'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500,
        ]);

        return [$school, $user, $appItem];
    }

    private function prPayload(School $school, AppItem $appItem, float $quantity, float $unitPrice = 250): array
    {
        return [
            'school_id' => $school->id, 'purpose' => 'Office supplies', 'entity_name' => 'DepEd', 'department_name' => $school->name,
            'request_date' => '2026-03-01', 'source_of_fund' => 'MOOE', 'transaction_description' => null, 'section' => null,
            'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null, 'extra_blank_rows' => 0,
            'items' => [['app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => $quantity, 'unit' => 'ream', 'unit_price' => $unitPrice]],
        ];
    }

    public function test_pr_line_is_linked_to_its_app_item(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha');

        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 4))->assertSessionHasNoErrors();

        $this->assertSame($appItem->id, ProcurementRequestItem::firstOrFail()->app_item_id);
        $this->assertSame(1, $appItem->requestItems()->count());
        $transaction = $appItem->ppmpItem->plan->transaction;
        $this->assertTrue($transaction->events()->where('action', 'pr_linked')->exists());
    }

    public function test_planning_page_shows_requested_quantity_and_pr_number(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha');
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 4))->assertSessionHasNoErrors();
        $number = ProcurementRequest::firstOrFail()->request_number;

        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()->assertSee('4 of 10 ream')->assertSee($number);
    }

    public function test_pr_cannot_exceed_remaining_app_quantity_or_cost(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha');

        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 6))->assertSessionHasNoErrors();
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 5))->assertSessionHasErrors('items');
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 4, 300))->assertSessionHasErrors('items');
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 4))->assertSessionHasNoErrors();
        $this->assertSame(2, ProcurementRequest::count());
    }

    public function test_editing_a_pr_does_not_count_its_own_lines_twice(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha');
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 10))->assertSessionHasNoErrors();
        $pr = ProcurementRequest::firstOrFail();

        $this->put(route('procurement.update', $pr), $this->prPayload($school, $appItem, 10))->assertSessionHasNoErrors();
        $this->assertSame(1, ProcurementRequestItem::count());
    }

    public function test_rejected_pr_releases_its_app_quantity(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha');
        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 10))->assertSessionHasNoErrors();
        ProcurementRequest::firstOrFail()->update(['status' => 'rejected']);

        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 10))->assertSessionHasNoErrors();
    }

    public function test_pr_cannot_link_to_an_unapproved_app(): void
    {
        [$school, , $appItem] = $this->tenantWithApprovedApp('alpha', 'draft');

        $this->post(route('procurement.store'), $this->prPayload($school, $appItem, 1))->assertSessionHasErrors('items');
        $this->assertSame(0, ProcurementRequest::count());
    }

    public function test_pr_cannot_link_to_another_organizations_app_item(): void
    {
        [, , $foreignItem] = $this->tenantWithApprovedApp('bravo');
        [$school] = $this->tenantWithApprovedApp('alpha');

        $this->post(route('procurement.store'), $this->prPayload($school, $foreignItem, 1))->assertSessionHasErrors('items');
        $this->assertSame(0, ProcurementRequest::count());
    }

    public function test_pr_form_offers_only_own_approved_app_items(): void
    {
        [, $user] = $this->tenantWithApprovedApp('alpha');
        $this->tenantWithApprovedApp('bravo');
        $this->actingAs($user);

        $this->get(route('procurement.create'))->assertOk()->assertSee('Bond paper')->assertViewHas('appItems', fn ($items) => count($items) === 1);
    }
}
