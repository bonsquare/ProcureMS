<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRequestWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_workspace_exposes_stage_sections_owner_and_next_action(): void
    {
        $organization = Organization::create(['name' => 'Alpha', 'slug' => 'alpha-request-workspace', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ARW', 'name' => 'Alpha School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin', 'name' => 'Maria Santos']);
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-0037', 'title' => 'Science laboratory supplies', 'amount' => 148750, 'status' => 'approved']);
        ProcurementRequestItem::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'name' => 'Microscope', 'quantity' => 2, 'unit' => 'unit', 'unit_price' => 20000, 'total' => 40000]);

        $response = $this->actingAs($user)->get(route('procurement.show', [$request, 'section' => 'documents']));

        $response->assertOk()->assertSee('PR-2026-0037')->assertSee('Science laboratory supplies')->assertSee('Alpha School')->assertSee('Maria Santos')->assertSee('₱148,750.00')
            ->assertSee('Summary')->assertSee('Items')->assertSee('Documents')->assertSee('Activity')->assertSee('Create or review quotations')->assertSee('Request for Quotation');
        $this->assertSame(7, substr_count($response->getContent(), 'class="civic-stage '));
        $response->assertSee('Canvass current stage', false);
    }

    public function test_approver_sees_and_can_use_procurement_decision_buttons(): void
    {
        $organization = Organization::create(['name' => 'Beta', 'slug' => 'beta-request-approval', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'BRA', 'name' => 'Beta School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now(), 'subscription_end' => now()->addMonth()]);
        $requester = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'office_user']);
        $approver = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'approver']);
        $procurementRequest = ProcurementRequest::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'requested_by' => $requester->id,
            'request_number' => 'PR-2026-APPROVE',
            'title' => 'Approval workflow supplies',
            'amount' => 5000,
            'status' => 'submitted',
        ]);

        $this->actingAs($approver)->get(route('procurement.show', $procurementRequest))
            ->assertOk()
            ->assertSee('Approve request')
            ->assertSee('Return request')
            ->assertSee('Reject request');

        $this->patch(route('procurement.status', $procurementRequest), ['status' => 'approved'])
            ->assertRedirect(route('procurement.show', $procurementRequest));

        $this->assertDatabaseHas('procurement_requests', ['id' => $procurementRequest->id, 'status' => 'approved']);
        $this->assertNotNull($procurementRequest->fresh()->approved_at);
    }

    public function test_document_preview_renders_the_official_page_without_saving_anything(): void
    {
        $organization = Organization::create(['name' => 'Gamma', 'slug' => 'gamma-preview', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'GPV', 'name' => 'Gamma School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-PREV', 'title' => 'Preview supplies', 'amount' => 1000, 'status' => 'approved']);

        $this->actingAs($user)->post(route('procurement.documents.preview', $pr), ['document_type' => 'request_for_quotation', 'document_date' => '2026-10-09'])
            ->assertOk()->assertSee('data-official-page', false);

        $this->assertDatabaseCount('procurement_documents', 0);
    }

    public function test_request_cannot_be_marked_complete_while_core_documents_are_missing(): void
    {
        $organization = Organization::create(['name' => 'Delta', 'slug' => 'delta-complete', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'DCM', 'name' => 'Delta School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-DONE', 'title' => 'Incomplete supplies', 'amount' => 1000, 'status' => 'for_canvass']);

        $this->actingAs($user)->patch(route('procurement.status', $pr), ['status' => 'completed'])
            ->assertRedirect(route('procurement.show', $pr))->assertSessionHas('error');

        $this->assertDatabaseHas('procurement_requests', ['id' => $pr->id, 'status' => 'for_canvass']);
    }

    public function test_start_canvass_sits_with_the_next_action_and_opens_the_documents(): void
    {
        $organization = Organization::create(['name' => 'Epsilon', 'slug' => 'epsilon-canvass', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ECV', 'name' => 'Epsilon School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-CANV', 'title' => 'Canvass supplies', 'amount' => 1000, 'status' => 'approved']);

        $this->actingAs($user)->get(route('procurement.show', $pr))->assertOk()
            ->assertSee('Next action')->assertSee('Start canvass')->assertDontSee('Continue workflow');

        $this->patch(route('procurement.status', $pr), ['status' => 'for_canvass'])->assertRedirect(route('procurement.documents', $pr));
        $page = $this->get(route('procurement.documents', $pr))->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, 'status updated to For Canvass'), 'the success message appears once');
        $this->assertDatabaseHas('procurement_requests', ['id' => $pr->id, 'status' => 'for_canvass']);
    }

    public function test_next_action_buttons_follow_the_stage_not_just_the_status(): void
    {
        $organization = Organization::create(['name' => 'Zeta', 'slug' => 'zeta-stage', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ZST', 'name' => 'Zeta School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-STAGE', 'title' => 'Stage supplies', 'amount' => 1000, 'status' => 'approved']);
        foreach (['purchase_order', 'inspection_acceptance_report'] as $index => $type) {
            ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => $type, 'document_number' => 'DOC-2026-00'.($index + 1), 'document_date' => now(), 'status' => 'prepared']);
        }

        $this->actingAs($user)->get(route('procurement.show', $pr))->assertOk()
            ->assertSee('Reconcile delivery')->assertSee('Open receiving documents')->assertDontSee('Start canvass');
    }
}
