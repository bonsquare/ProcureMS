<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\MasterTransaction;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\SipProject;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-012: one school walks the whole chain through the real routes, SIP to AIP to SOB to APP to Purchase Request to Purchase Order,
 * and every step keeps its link to the one before it and to the master transaction.
 */
class PlanningToPurchaseOrderChainTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: School, 2: User} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Chain Org', 'slug' => 'chain-org', 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'CHAIN', 'name' => 'Chain School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    public function test_one_school_walks_from_the_sip_to_a_purchase_order_with_every_link_kept(): void
    {
        [$organization, $school, $user] = $this->tenant();
        $this->actingAs($user);

        // 1. SIP
        $this->post(route('planning.sip.store'), ['school_id' => $school->id, 'school_year' => 2026, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'project' => 'Reading Program'])->assertSessionHasNoErrors();
        $sip = SipProject::firstOrFail();
        $this->assertNotNull($sip->master_transaction_id);

        // 2. AIP linked to the SIP program
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'status' => 'approved']);
        $this->post(route('planning.sip.link-aip', $sip), ['aip_id' => $aip->id])->assertSessionHasNoErrors();
        $kra = AipKra::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'program' => 'Reading Program']);
        $activity = AipActivity::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Buy reading materials', 'physical_target' => 1, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0]);
        $account = ChartOfAccount::create(['organization_id' => $organization->id, 'code' => '5-02-03-010', 'title' => 'Office Supplies Expenses', 'category' => 'Expense']);
        $transactionId = $aip->fresh()->master_transaction_id;
        $this->assertNotNull($transactionId);

        // 3. SOB for the quarter, one item, approved: the Budget line is written
        $this->post(route('planning.sob.store'), ['school_id' => $school->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE'])->assertSessionHasNoErrors();
        $sob = SobPlan::firstOrFail();
        $this->assertSame($transactionId, $sob->master_transaction_id);
        $this->post(route('planning.sob.items.store', $sob), [
            'aip_activity_id' => $activity->id, 'chart_of_account_id' => $account->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 250,
        ])->assertSessionHasNoErrors();
        $this->post(route('planning.sob.approve', $sob))->assertSessionHasNoErrors();
        $this->assertSame('approved', $sob->fresh()->status);
        $line = BudgetAllocation::firstOrFail();
        $this->assertEquals(2500, $line->amount);
        $this->assertSame($school->id, $line->school_id);

        // 4. APP generated from the approved SOB and approved
        $sobItem = SobItem::firstOrFail();
        $this->post(route('planning.app.generate'), ['school_id' => $school->id, 'fiscal_year' => 2026, 'sob_item_ids' => [$sobItem->id]])->assertSessionHasNoErrors();
        $app = AppPlan::firstOrFail();
        $appItem = AppItem::firstOrFail();
        $this->assertSame($sobItem->id, $appItem->sob_item_id);
        $this->assertEquals(10, $appItem->quantity);
        $this->post(route('planning.app.approve', $app))->assertSessionHasNoErrors();
        $this->assertSame('approved', $app->fresh()->status);

        // 5. Purchase Request from the SOB activity, linked to the APP item
        $this->post(route('procurement.store'), [
            'school_id' => $school->id, 'purpose' => 'Reading materials', 'entity_name' => 'DepEd', 'department_name' => $school->name, 'request_date' => '2026-03-01',
            'source_of_fund' => 'MOOE', 'transaction_description' => null, 'section' => null, 'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null,
            'sob_activity_id' => $activity->id, 'extra_blank_rows' => 0,
            'items' => [['app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => 10, 'unit' => 'ream', 'unit_price' => 250]],
        ])->assertSessionHasNoErrors();
        $pr = ProcurementRequest::firstOrFail();
        $prItem = ProcurementRequestItem::firstOrFail();
        $this->assertSame($appItem->id, $prItem->app_item_id);
        $this->assertSame($school->id, $pr->school_id);
        $this->assertSame($transactionId, $pr->master_transaction_id);

        // The whole budget line and APP quantity are now requested: a second request for the same item is refused (here by the budget check).
        $this->post(route('procurement.store'), [
            'school_id' => $school->id, 'purpose' => 'Too many', 'entity_name' => 'DepEd', 'department_name' => $school->name, 'request_date' => '2026-03-02',
            'source_of_fund' => 'MOOE', 'transaction_description' => null, 'section' => null, 'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null, 'extra_blank_rows' => 0,
            'items' => [['app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => 1, 'unit' => 'ream', 'unit_price' => 250]],
        ])->assertSessionHasNoErrors()->assertSessionHas('warning');
        // A request beyond the SOB is saved with a warning (owner's decision 2026-10-25); it is set aside so the chain goes on with the first one.
        ProcurementRequest::latest('id')->first()->delete();
        $this->assertSame(1, ProcurementRequest::count());

        // 6. Approval, then RFQ, Abstract, Notice to Award, Notice to Proceed and Purchase Order in their order
        $this->patch(route('procurement.status', $pr), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $pr->fresh()->status);

        $document = fn (string $type, array $extra = []) => $this->post(route('procurement.documents.store', $pr), ['document_type' => $type, 'document_date' => '2026-03-05'] + $extra);

        $document('purchase_order')->assertSessionHasErrors('document_type');
        $this->assertSame(0, ProcurementDocument::where('document_type', 'purchase_order')->count());

        $document('request_for_quotation')->assertSessionHasNoErrors();
        $document('abstract_of_bids_quotation', ['bidders' => [['name' => 'Winning Trading', 'prices' => [$prItem->id => 250]], ['name' => 'Second Trading', 'prices' => [$prItem->id => 270]]]])->assertSessionHasNoErrors();
        $document('notice_to_award')->assertSessionHasNoErrors();
        $document('notice_to_proceed', ['delivery_days' => 15])->assertSessionHasNoErrors();
        $document('purchase_order')->assertSessionHasNoErrors();

        $po = ProcurementDocument::where('document_type', 'purchase_order')->firstOrFail();
        $this->assertSame($pr->id, $po->procurement_request_id);
        $this->assertSame('Winning Trading', $po->supplier_or_recipient);
        $this->assertNotEmpty($po->document_number);
        $this->assertSame(
            ['request_for_quotation', 'abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed', 'purchase_order'],
            ProcurementDocument::orderBy('id')->pluck('document_type')->all(),
        );
        $printed = $this->get(route('procurement.documents.print', [$pr, $po]))->assertOk()->getContent();
        $this->assertStringContainsStringIgnoringCase('Winning Trading', $printed);
        $this->assertStringContainsString($po->document_number, $printed);

        // The same transaction carries the planning events and the PR link
        $actions = MasterTransaction::findOrFail($transactionId)->events()->pluck('action')->all();
        $this->assertContains('pr_linked', $actions);
    }
}
