<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementSchoolFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_user_can_filter_every_procurement_workspace_by_school(): void
    {
        [$alphaOrganization, $alphaSchool, $alphaUser] = $this->tenant('Alpha');
        [$betaOrganization, $betaSchool, $betaUser] = $this->tenant('Beta');
        $alphaRequest = $this->procurementRequest($alphaOrganization, $alphaSchool, $alphaUser, 'ALPHA-PR');
        $betaRequest = $this->procurementRequest($betaOrganization, $betaSchool, $betaUser, 'BETA-PR');
        ProcurementDocument::create([
            'organization_id' => $alphaOrganization->id,
            'procurement_request_id' => $alphaRequest->id,
            'created_by' => $alphaUser->id,
            'document_type' => 'purchase_order',
            'document_number' => 'ALPHA-PO',
            'document_date' => now(),
            'status' => 'prepared',
        ]);
        ProcurementDocument::create([
            'organization_id' => $betaOrganization->id,
            'procurement_request_id' => $betaRequest->id,
            'created_by' => $betaUser->id,
            'document_type' => 'purchase_order',
            'document_number' => 'BETA-PO',
            'document_date' => now(),
            'status' => 'prepared',
        ]);
        Supplier::create([
            'organization_id' => $alphaOrganization->id,
            'school_id' => $alphaSchool->id,
            'business_name' => 'Alpha Supply Co.',
            'tax_type' => 'vat',
            'status' => 'active',
        ]);
        Supplier::create([
            'organization_id' => $betaOrganization->id,
            'school_id' => $betaSchool->id,
            'business_name' => 'Beta Supply Co.',
            'tax_type' => 'vat',
            'status' => 'active',
        ]);
        $master = User::factory()->create(['role' => 'master_user']);

        foreach ([
            route('procurement') => ['BETA-PR', 'ALPHA-PR'],
            route('procurement.requests') => ['BETA-PR', 'ALPHA-PR'],
            route('procurement.documents.index') => ['BETA-PO', 'ALPHA-PO'],
            route('procurement.receiving') => ['BETA-PR', 'ALPHA-PR'],
            route('suppliers') => ['Beta Supply Co.', 'Alpha Supply Co.'],
        ] as $route => [$visibleRecord, $hiddenRecord]) {
            $this->actingAs($master)->get($route.'?school_id='.$betaSchool->id)
                ->assertOk()
                ->assertSee($visibleRecord)
                ->assertDontSee($hiddenRecord);
        }
    }

    private function tenant(string $name): array
    {
        $organization = Organization::create(['name' => "$name Organization", 'slug' => strtolower($name).'-school-filter', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($name), 'name' => "$name School", 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function procurementRequest(Organization $organization, School $school, User $user, string $number): ProcurementRequest
    {
        $request = ProcurementRequest::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'requested_by' => $user->id,
            'request_number' => $number,
            'title' => "$number supplies",
            'amount' => 1000,
            'status' => 'approved',
        ]);
        ProcurementRequestItem::create([
            'organization_id' => $organization->id,
            'procurement_request_id' => $request->id,
            'name' => 'Bond paper',
            'quantity' => 10,
            'unit' => 'ream',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        return $request;
    }
}
