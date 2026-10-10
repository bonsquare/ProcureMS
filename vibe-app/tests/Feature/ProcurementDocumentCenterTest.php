<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementDocumentCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_document_center_is_scoped_and_exposes_operational_details(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        [$otherOrganization, $otherSchool, $otherUser] = $this->tenant('Beta');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $otherRequest = $this->procurementRequest($otherOrganization, $otherSchool, $otherUser, 'BETA-PR-001');
        $this->document($organization, $request, $user, 'purchase_order', 'PO-2026-001');
        $this->document($otherOrganization, $otherRequest, $otherUser, 'purchase_order', 'PO-2026-999');

        $this->actingAs($user)->get(route('procurement.documents.index'))
            ->assertOk()
            ->assertViewIs('procurement-documents-index')
            ->assertSee('Document Center')
            ->assertSee('ALPHA-PR-001')
            ->assertSee('PO-2026-001')
            ->assertSee('Purchase Order')
            ->assertSee('civic-mobile-cards', false)
            ->assertSee($user->name)
            ->assertSee('Process')
            ->assertDontSee('PO-2026-999');
    }

    public function test_document_center_filters_by_type_request_and_school_for_master_users(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        [$otherOrganization, $otherSchool, $otherUser] = $this->tenant('Beta');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $otherRequest = $this->procurementRequest($otherOrganization, $otherSchool, $otherUser, 'BETA-PR-001');
        $this->document($organization, $request, $user, 'purchase_order', 'PO-2026-001');
        $this->document($organization, $request, $user, 'request_for_quotation', 'RFQ-2026-001');
        $this->document($otherOrganization, $otherRequest, $otherUser, 'purchase_order', 'PO-2026-999');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('procurement.documents.index', [
            'type' => 'purchase_order',
            'request' => 'ALPHA-PR-001',
            'school_id' => $school->id,
        ]))->assertOk()
            ->assertSee('PO-2026-001')
            ->assertDontSee('RFQ-2026-001')
            ->assertDontSee('PO-2026-999');
    }

    public function test_request_document_center_shows_checklist_missing_requirements_and_existing_actions(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $this->document($organization, $request, $user, 'purchase_order', 'PO-2026-001');

        $this->actingAs($user)->get(route('procurement.documents', $request))
            ->assertOk()
            ->assertSee('Document checklist')
            ->assertSee('Missing requirement')
            ->assertSee('View &amp; Print', false)
            ->assertSee('Edit')
            ->assertSee('Create')
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false);
    }

    public function test_a_request_with_several_documents_is_listed_once_with_its_latest_document(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $rfq = $this->document($organization, $request, $user, 'request_for_quotation', 'RFQ-2026-001');
        $abstract = $this->document($organization, $request, $user, 'abstract_of_bids_quotation', 'ABQ-2026-001');
        $rfq->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $abstract->forceFill(['updated_at' => now()])->saveQuietly();
        $other = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-002');
        $this->document($organization, $other, $user, 'purchase_order', 'PO-2026-001');

        $page = $this->actingAs($user)->get(route('procurement.documents.index'))->assertOk();
        $this->assertSame(2, $page->viewData('documents')->total());
        $page->assertSee('ABQ-2026-001')->assertDontSee('RFQ-2026-001')->assertSee('+1 more')->assertSee('PO-2026-001');
    }

    public function test_the_document_type_filter_shows_the_matching_document_of_each_request(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $this->document($organization, $request, $user, 'request_for_quotation', 'RFQ-2026-001');
        $abstract = $this->document($organization, $request, $user, 'abstract_of_bids_quotation', 'ABQ-2026-001');
        $abstract->forceFill(['updated_at' => now()->addDay()])->saveQuietly();

        $this->actingAs($user)->get(route('procurement.documents.index', ['type' => 'request_for_quotation']))->assertOk()
            ->assertSee('RFQ-2026-001')->assertDontSee('ABQ-2026-001');
    }

    public function test_listing_by_request_keeps_each_school_apart(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        [$otherOrganization, $otherSchool, $otherUser] = $this->tenant('Beta');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');
        $otherRequest = $this->procurementRequest($otherOrganization, $otherSchool, $otherUser, 'BETA-PR-001');
        $this->document($organization, $request, $user, 'request_for_quotation', 'RFQ-2026-001');
        $this->document($otherOrganization, $otherRequest, $otherUser, 'request_for_quotation', 'RFQ-2026-999');
        $this->document($otherOrganization, $otherRequest, $otherUser, 'abstract_of_bids_quotation', 'ABQ-2026-999');

        $page = $this->actingAs($user)->get(route('procurement.documents.index'))->assertOk();
        $this->assertSame(1, $page->viewData('documents')->total());
        $page->assertSee('RFQ-2026-001')->assertDontSee('999')->assertDontSee('+1 more');
    }

    private function tenant(string $name): array
    {
        $organization = Organization::create(['name' => "$name Organization", 'slug' => strtolower($name).'-organization', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($name), 'name' => "$name School", 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function procurementRequest(Organization $organization, School $school, User $user, string $number): ProcurementRequest
    {
        return ProcurementRequest::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'requested_by' => $user->id,
            'request_number' => $number,
            'title' => "$number supplies",
            'amount' => 1000,
            'status' => 'approved',
        ]);
    }

    private function document(Organization $organization, ProcurementRequest $request, User $user, string $type, string $number): ProcurementDocument
    {
        return ProcurementDocument::create([
            'organization_id' => $organization->id,
            'procurement_request_id' => $request->id,
            'created_by' => $user->id,
            'document_type' => $type,
            'document_number' => $number,
            'document_date' => now()->toDateString(),
            'status' => 'prepared',
        ]);
    }

    public function test_document_center_always_offers_process_with_a_view_icon_and_shows_progress(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $pending = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-PENDING');
        $pending->update(['status' => 'pending_approval']);
        $rfq = $this->document($organization, $pending, $user, 'request_for_quotation', 'RFQ-2026-050');

        $this->actingAs($user)->get(route('procurement.documents.index'))->assertOk()
            ->assertSee('Process')->assertSee(route('procurement.documents', $pending), false)
            ->assertSee('On processing')->assertSee('1 of 8 steps')
            ->assertSee('aria-label="View RFQ-2026-050"', false)->assertSee(route('procurement.documents.print', [$pending, $rfq]), false)
            ->assertDontSee('View &amp; Print', false)->assertDontSee('>Edit<', false)->assertDontSee('Delivered');

        foreach (['abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed', 'purchase_order', 'inspection_acceptance_report', 'inventory_acknowledgement_receipt_supplies', 'requisition_issuance_slip'] as $index => $type) {
            $this->document($organization, $pending, $user, $type, 'DOC-2026-'.sprintf('%03d', $index + 1));
        }

        // Every document is prepared, but the request itself has not been marked complete yet: stage and progress agree.
        $this->get(route('procurement.documents.index'))->assertOk()->assertSee('Receiving')->assertSee('8 of 8 steps')->assertSee('On processing')->assertDontSee('Delivered');

        $pending->update(['status' => 'completed']);
        $this->get(route('procurement.documents.index'))->assertOk()->assertSee('Complete')->assertSee('8 of 8 steps')->assertSee('Delivered')->assertDontSee('On processing');
    }

    public function test_receiving_documents_live_under_the_receiving_stage(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-RECV');

        $procurement = $this->actingAs($user)->get(route('procurement.documents', $request))->assertOk()
            ->assertSee('Request for Quotation')->assertSee('Purchase Order')->assertSee('Notice to Proceed')
            ->assertSee('Receiving documents')->assertSee(route('procurement.documents', [$request, 'stage' => 'receiving']), false);
        foreach (['Inventory Custodian Slip', 'Requisition and Issuance Slip', 'Property Acknowledgement Receipt', 'Inventory and Acknowledgement Receipt of Supplies'] as $label) {
            $procurement->assertDontSee('>'.$label.'<', false);
        }

        $this->get(route('procurement.documents', [$request, 'stage' => 'receiving']))->assertOk()
            ->assertSee('Inspection and Acceptance Report')->assertSee('Inventory Custodian Slip')->assertSee('Requisition and Issuance Slip')
            ->assertSee('Property Acknowledgement Receipt')->assertSee('Inventory and Acknowledgement Receipt of Supplies')
            ->assertDontSee('>Request for Quotation<', false)->assertDontSee('>Purchase Order<', false);

        $this->get(route('procurement.receiving'))->assertOk()
            ->assertSee(route('procurement.documents', [$request, 'stage' => 'receiving']), false);
    }

    public function test_documents_follow_the_workflow_order(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-ORDER');
        $request->update(['status' => 'submitted']);
        $service = app(ProcurementWorkspaceService::class);

        // Nothing can be prepared before the purchase request is approved.
        $this->assertSame('Approve the purchase request first.', $service->blockedReason($request->fresh(), 'request_for_quotation'));

        $request->update(['status' => 'approved']);
        $this->assertNull($service->blockedReason($request->fresh(), 'request_for_quotation'));
        $this->assertSame('Prepare the Request for Quotation first.', $service->blockedReason($request->fresh(), 'abstract_of_bids_quotation'));

        $this->actingAs($user)->post(route('procurement.documents.store', $request), ['document_type' => 'abstract_of_bids_quotation', 'document_date' => '2026-10-09'])
            ->assertSessionHasErrors('document_type');
        $this->assertDatabaseMissing('procurement_documents', ['procurement_request_id' => $request->id, 'document_type' => 'abstract_of_bids_quotation']);

        foreach (['request_for_quotation', 'abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed', 'purchase_order', 'inspection_acceptance_report', 'inventory_acknowledgement_receipt_supplies', 'requisition_issuance_slip'] as $index => $type) {
            $this->assertNull($service->blockedReason($request->fresh(), $type), $type.' should be open once the one before it exists');
            $this->document($organization, $request, $user, $type, 'DOC-2026-'.sprintf('%03d', $index + 1));
        }

        // Optional slips follow the RIS and never hold up completion.
        $this->assertNull($service->blockedReason($request->fresh(), 'inventory_custodian_slip'));
        $this->assertNull($service->blockedReason($request->fresh(), 'property_acknowledgement_receipt'));
        $this->assertSame(8, $service->present($request->fresh()->load('documents'))['documents']['completed']);
    }
}
