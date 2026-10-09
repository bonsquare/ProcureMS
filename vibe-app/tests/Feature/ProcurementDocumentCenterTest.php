<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
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
            ->assertSee($user->name)
            ->assertSee('View &amp; Print', false)
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
}
