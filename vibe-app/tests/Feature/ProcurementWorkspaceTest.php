<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_procurement_areas_are_deep_linkable(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->procurementRequest($organization, $school, $user, 'ALPHA-PR-001');

        $this->actingAs($user)->get(route('procurement'))->assertOk()->assertViewHas('activeProcurementArea', 'overview');
        $this->actingAs($user)->get(route('procurement.requests'))->assertOk()->assertViewHas('activeProcurementArea', 'requests');
        $this->actingAs($user)->get(route('procurement.show', $request))->assertOk()->assertViewHas('activeProcurementArea', 'requests');
        $this->actingAs($user)->get(route('suppliers'))->assertOk()->assertViewHas('activeProcurementArea', 'suppliers');
        $this->actingAs($user)->get(route('procurement.documents.index'))->assertOk()->assertViewHas('activeProcurementArea', 'documents');
        $this->actingAs($user)->get(route('procurement.receiving'))->assertOk()->assertViewHas('activeProcurementArea', 'receiving');
    }

    public function test_school_user_procurement_queries_exclude_another_organization(): void
    {
        [$alphaOrg, $alphaSchool, $alphaUser] = $this->tenant('Alpha');
        [$betaOrg, $betaSchool, $betaUser] = $this->tenant('Beta');
        $alphaRequest = $this->procurementRequest($alphaOrg, $alphaSchool, $alphaUser, 'ALPHA-PR-001');
        $betaRequest = $this->procurementRequest($betaOrg, $betaSchool, $betaUser, 'BETA-PR-001');
        $this->supplier($alphaOrg, $alphaSchool, 'Alpha Trading');
        $this->supplier($betaOrg, $betaSchool, 'Beta Trading');
        $this->document($alphaOrg, $alphaRequest, $alphaUser, 'ALPHA-PO-001');
        $this->document($betaOrg, $betaRequest, $betaUser, 'BETA-PO-001');

        $this->actingAs($alphaUser)->get(route('procurement.requests'))
            ->assertViewHas('procurementRequests', fn ($records) => $records->pluck('request_number')->all() === ['ALPHA-PR-001']);
        $this->actingAs($alphaUser)->get(route('suppliers'))
            ->assertViewHas('suppliers', fn ($records) => $records->pluck('business_name')->all() === ['Alpha Trading']);
        $this->actingAs($alphaUser)->get(route('procurement.documents.index'))
            ->assertViewHas('documents', fn ($records) => $records->pluck('document_number')->all() === ['ALPHA-PO-001']);
        $this->actingAs($alphaUser)->get(route('procurement.receiving'))
            ->assertViewHas('receivingRequests', fn ($records) => $records->pluck('request_number')->all() === ['ALPHA-PR-001']);
    }

    public function test_master_user_can_see_procurement_records_across_organizations(): void
    {
        [$alphaOrg, $alphaSchool, $alphaUser] = $this->tenant('Alpha');
        [$betaOrg, $betaSchool, $betaUser] = $this->tenant('Beta');
        $this->procurementRequest($alphaOrg, $alphaSchool, $alphaUser, 'ALPHA-PR-001');
        $this->procurementRequest($betaOrg, $betaSchool, $betaUser, 'BETA-PR-001');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('procurement.requests'))
            ->assertViewHas('procurementRequests', fn ($records) => $records->pluck('request_number')->sort()->values()->all() === ['ALPHA-PR-001', 'BETA-PR-001']);
    }

    public function test_school_user_cannot_open_a_request_from_another_organization(): void
    {
        [, , $alphaUser] = $this->tenant('Alpha');
        [$betaOrg, $betaSchool, $betaUser] = $this->tenant('Beta');
        $betaRequest = $this->procurementRequest($betaOrg, $betaSchool, $betaUser, 'BETA-PR-001');

        $this->actingAs($alphaUser)->get(route('procurement.show', $betaRequest))->assertNotFound();
    }

    private function tenant(string $name): array
    {
        $organization = Organization::create([
            'name' => "$name Organization",
            'slug' => strtolower($name).'-organization',
            'status' => 'active',
        ]);
        $school = School::create([
            'organization_id' => $organization->id,
            'code' => strtoupper($name).'-SCHOOL',
            'name' => "$name School",
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'role' => 'school_admin',
        ]);

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

    private function supplier(Organization $organization, School $school, string $name): Supplier
    {
        return Supplier::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'business_name' => $name,
            'tax_type' => 'vat',
            'tax_rate' => 12,
            'status' => 'active',
        ]);
    }

    private function document(Organization $organization, ProcurementRequest $request, User $user, string $number): ProcurementDocument
    {
        return ProcurementDocument::create([
            'organization_id' => $organization->id,
            'procurement_request_id' => $request->id,
            'created_by' => $user->id,
            'document_type' => 'purchase_order',
            'document_number' => $number,
            'document_date' => now()->toDateString(),
            'status' => 'prepared',
        ]);
    }
}
