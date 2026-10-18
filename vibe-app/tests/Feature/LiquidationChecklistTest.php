<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use App\Services\LiquidationChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The papers of a liquidation: ticked by the person who manages Accounting, needed before the ORS is approved and before the DV is created. */
class LiquidationChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        $organization = Organization::firstOrCreate(['slug' => 'papers-org'], ['name' => 'Papers Org', 'status' => 'active']);

        $school = School::firstOrCreate(['code' => 'PPR'], ['organization_id' => $organization->id, 'name' => 'Papers School', 'status' => 'active']);
        \App\Models\Subscription::firstOrCreate(['organization_id' => $organization->id, 'school_id' => $school->id], ['plan' => 'professional', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth()]);

        return $school;
    }

    private function ors(array $extra = [], ?ProcurementRequest $request = null): LiquidationReport
    {
        $school = $this->school();

        return LiquidationReport::create($extra + [
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'procurement_request_id' => $request?->id, 'report_number' => 'LR-P-'.uniqid(), 'ors_number' => 'ORS-P-'.uniqid(),
            'amount' => 1000, 'status' => 'for_review', 'payee' => 'Supplier', 'purpose' => 'Electricity',
        ]);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function save(LiquidationReport $report, string $kind, array $papers = [])
    {
        return $this->put(route('accounting.papers', $report), ['ors_kind' => $kind, 'papers' => $papers]);
    }

    private function fresh(LiquidationReport $report): LiquidationReport
    {
        return $report->fresh(['procurementRequest.documents', 'documentChecks']);
    }

    public function test_the_kind_follows_the_purchase_request_unless_the_accountant_chooses_one(): void
    {
        $service = app(LiquidationChecklistService::class);
        $direct = $this->ors();
        $this->assertSame('direct', $service->kind($direct));
        $this->assertSame('travel', $service->kind($this->ors(['ors_kind' => 'travel'])));
        $request = ProcurementRequest::create(['organization_id' => $direct->organization_id, 'school_id' => $direct->school_id, 'requested_by' => $this->master()->id, 'request_number' => 'PR-P-1', 'title' => 'Supplies', 'amount' => 1000, 'status' => 'approved']);
        $this->assertSame('goods', $service->kind($this->ors([], $request)));
    }

    public function test_an_ors_cannot_be_approved_while_a_paper_of_its_kind_is_missing_and_the_papers_can_be_ticked_or_marked_not_required(): void
    {
        $report = $this->ors(['ors_kind' => 'travel']);
        $this->actingAs($this->master());

        $this->patch(route('accounting.review', $report), ['status' => 'approved'])->assertSessionHasErrors('papers');
        $this->assertSame('for_review', $report->fresh()->status);
        $this->assertStringContainsString('Travel order', session('errors')->first('papers'));

        $this->save($report, 'travel', ['travel_order' => ['state' => 'received', 'note' => 'TO 12'], 'itinerary_of_travel' => ['state' => 'not_required', 'note' => 'Local trip']])->assertSessionHasNoErrors();
        $this->patch(route('accounting.review', $report), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $report->fresh()->status);
        $this->assertSame('TO 12', $report->documentChecks()->where('item_key', 'travel_order')->value('note'));
    }

    public function test_the_dv_waits_for_the_papers_of_its_stage_and_a_manual_dv_without_an_ors_is_not_held(): void
    {
        $report = $this->ors(['ors_kind' => 'direct', 'status' => 'approved']);
        $this->actingAs($this->master());
        $dv = ['dv_date' => now()->toDateString(), 'payee' => 'Supplier', 'dv_particulars' => 'Electricity', 'payment_mode' => 'MDS Check', 'journal' => [
            ['account_code' => collect(ChartOfAccount::standardAccounts())->firstWhere('category', 'MOOE')['code'], 'debit' => '1000', 'credit' => ''],
            ['account_code' => '1010404000', 'debit' => '', 'credit' => '1000'],
        ]];

        $this->post(route('accounting.dv.store', $report), $dv)->assertSessionHasErrors('papers');
        $this->assertNull($report->fresh()->dv_number);

        $this->save($report, 'direct', ['proof_of_service' => ['state' => 'received'], 'billing_statement' => ['state' => 'received']])->assertSessionHasNoErrors();
        $this->post(route('accounting.dv.store', $report), $dv)->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->dv_number);

        $manual = $this->ors(['ors_number' => null, 'status' => 'approved']);
        $this->assertCount(0, app(LiquidationChecklistService::class)->missing($this->fresh($manual), 'dv'));
    }

    public function test_goods_read_the_procurement_documents_and_the_not_required_marks_by_themselves(): void
    {
        $school = $this->school();
        $owner = User::factory()->create(['role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);
        $request = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $owner->id, 'request_number' => 'PR-P-2', 'title' => 'Supplies', 'amount' => 1000, 'status' => 'approved']);
        $report = $this->ors([], $request);
        $service = app(LiquidationChecklistService::class);

        $this->assertEqualsCanonicalizing(['Request for Quotation', 'Abstract of Bids or Quotation', 'Notice to Award', 'Notice to Proceed', 'Purchase Order'], $service->missing($this->fresh($report), 'ors')->all());

        ProcurementDocument::create(['organization_id' => $school->organization_id, 'procurement_request_id' => $request->id, 'created_by' => $owner->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-P-1', 'document_date' => now(), 'status' => 'prepared']);
        $request->update(['not_required_documents' => ['request_for_quotation' => 'x', 'abstract_of_bids_quotation' => 'x', 'notice_to_award' => 'x', 'notice_to_proceed' => 'x']]);

        $this->assertCount(0, $service->missing($this->fresh($report), 'ors'));
        $this->assertEqualsCanonicalizing(['Inspection and Acceptance Report', 'Sales invoice or official receipt', 'Delivery receipt'], $service->missing($this->fresh($report), 'dv')->all());
    }

    public function test_pending_documents_says_which_papers_are_missing_and_only_the_accounting_manager_can_tick(): void
    {
        $report = $this->ors(['ors_kind' => 'direct']);
        $this->actingAs($this->master());
        $this->patch(route('accounting.review', $report), ['status' => 'pending_documents'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('Billing statement or official receipt', (string) $report->fresh()->accounting_remarks);

        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $report->organization_id, 'school_id' => $report->school_id]);
        $this->actingAs($viewer);
        $this->save($report, 'direct', ['billing_statement' => ['state' => 'received']])->assertForbidden();
        $this->assertSame(0, $report->documentChecks()->count());

        $this->actingAs($this->master());
        $this->save($report, 'nonsense')->assertSessionHasErrors('ors_kind');
    }

    public function test_a_school_adds_its_own_paper_which_then_holds_the_approval_until_it_is_ticked(): void
    {
        $school = $this->school();
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);
        $this->actingAs($admin);

        $this->post(route('school-settings.papers.store'), ['school_id' => $school->id, 'ors_kind' => 'direct', 'stage' => 'ors', 'label' => 'Barangay clearance'])->assertSessionHasNoErrors();
        $this->post(route('school-settings.papers.store'), ['school_id' => $school->id, 'ors_kind' => 'nonsense', 'stage' => 'ors', 'label' => 'X'])->assertSessionHasErrors('ors_kind');
        $item = $school->checklistItems()->firstOrFail();

        $report = $this->ors(['ors_kind' => 'direct']);
        $service = app(LiquidationChecklistService::class);
        $this->assertContains('Barangay clearance', $service->missing($this->fresh($report)->load('school.checklistItems'), 'ors')->all());
        $this->assertNotContains('Barangay clearance', $service->missing($this->fresh($this->ors(['ors_kind' => 'travel']))->load('school.checklistItems'), 'ors')->all(), 'only for its own kind');

        $this->save($report, 'direct', ['billing_statement' => ['state' => 'received'], $item->key() => ['state' => 'received', 'note' => 'Brgy 5']])->assertSessionHasNoErrors();
        $this->assertCount(0, $service->missing($this->fresh($report)->load('school.checklistItems'), 'ors'));
        $this->assertSame('Brgy 5', $report->documentChecks()->where('item_key', $item->key())->value('note'));

        $this->delete(route('school-settings.papers.destroy', $item), ['school_id' => $school->id])->assertSessionHasNoErrors();
        $this->assertSame(0, $school->checklistItems()->count());
    }

    public function test_only_the_school_that_owns_a_paper_can_remove_it_and_a_viewer_cannot_add_one(): void
    {
        $school = $this->school();
        $item = \App\Models\SchoolChecklistItem::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'ors_kind' => 'goods', 'stage' => 'dv', 'label' => 'Warranty card']);
        $otherOrg = Organization::create(['name' => 'Other Org', 'slug' => 'other-papers-org', 'status' => 'active']);
        $otherSchool = School::create(['organization_id' => $otherOrg->id, 'code' => 'OTH', 'name' => 'Other School', 'status' => 'active']);
        \App\Models\Subscription::create(['organization_id' => $otherOrg->id, 'school_id' => $otherSchool->id, 'plan' => 'professional', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth()]);
        $outsider = User::factory()->create(['role' => 'school_admin', 'organization_id' => $otherOrg->id, 'school_id' => $otherSchool->id]);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);

        $this->actingAs($outsider)->delete(route('school-settings.papers.destroy', $item), ['school_id' => $otherSchool->id])->assertNotFound();
        $this->actingAs($viewer)->post(route('school-settings.papers.store'), ['school_id' => $school->id, 'ors_kind' => 'goods', 'stage' => 'dv', 'label' => 'Y'])->assertForbidden();
        $this->assertSame(1, \App\Models\SchoolChecklistItem::count());
    }

    public function test_the_liquidation_page_and_the_settings_tab_list_the_papers_read_only(): void
    {
        $school = $this->school();
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);
        $this->ors(['ors_kind' => 'travel']);
        \App\Models\SchoolChecklistItem::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'ors_kind' => 'travel', 'stage' => 'ors', 'label' => 'Memorandum of travel']);

        $this->actingAs($admin)->get(route('liquidation'))->assertOk()->assertSee('Papers 0 of 5')->assertSee('Travel order')->assertSee('Memorandum of travel');
        $this->get(route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'papers', 'school_id' => $school->id]))->assertOk()->assertSee('Memorandum of travel')->assertSee('Add a paper');
    }
}
