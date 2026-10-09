<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementReceivingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_workspace_prioritizes_partial_deliveries_and_shows_missing_documents(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $complete = $this->requestWithItem($organization, $school, $user, 'PR-COMPLETE');
        $partial = $this->requestWithItem($organization, $school, $user, 'PR-PARTIAL');
        $missing = $this->requestWithItem($organization, $school, $user, 'PR-MISSING');
        $this->receivingDocuments($organization, $complete, $user, 10);
        $this->receivingDocuments($organization, $partial, $user, 4);

        [$otherOrganization, $otherSchool, $otherUser] = $this->tenant('Beta');
        $hidden = $this->requestWithItem($otherOrganization, $otherSchool, $otherUser, 'PR-HIDDEN');
        $this->receivingDocuments($otherOrganization, $hidden, $otherUser, 2);

        $response = $this->actingAs($user)->get(route('procurement.receiving'));

        $response->assertOk()->assertViewIs('procurement-receiving')
            ->assertSee('civic-mobile-cards', false)
            ->assertSeeInOrder(['PR-PARTIAL', 'PR-COMPLETE'])
            ->assertSee('Partial delivery')->assertSee('Delivery complete')
            ->assertSee('Missing PO and IAR')->assertSee('PR-MISSING')
            ->assertSee('Ordered')->assertSee('Received')->assertSee('Balance')
            ->assertSee(route('procurement.show', $partial), false)
            ->assertSee(route('procurement.documents', $partial), false)
            ->assertDontSee('PR-HIDDEN');
    }

    public function test_operational_and_printable_reconciliation_share_the_same_totals(): void
    {
        [$organization, $school, $user] = $this->tenant('Alpha');
        $request = $this->requestWithItem($organization, $school, $user, 'PR-PARTIAL');
        $this->receivingDocuments($organization, $request, $user, 4);

        $this->actingAs($user)->get(route('procurement.receiving'))
            ->assertOk()->assertSee('10')->assertSee('4')->assertSee('6');
        $this->actingAs($user)->get(route('procurement.delivery-reconciliation', $request))
            ->assertOk()->assertSee('10')->assertSee('4')->assertSee('6')->assertSee('Partial');
    }

    private function tenant(string $name): array
    {
        $organization = Organization::create(['name' => "$name Organization", 'slug' => strtolower($name).'-organization', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($name), 'name' => "$name School", 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function requestWithItem(Organization $organization, School $school, User $user, string $number): ProcurementRequest
    {
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => $number, 'title' => "$number supplies", 'amount' => 1000, 'status' => 'approved']);
        ProcurementRequestItem::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'name' => 'Bond paper', 'quantity' => 10, 'unit' => 'ream', 'unit_price' => 100, 'total' => 1000]);

        return $request;
    }

    private function receivingDocuments(Organization $organization, ProcurementRequest $request, User $user, int $received): void
    {
        ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-'.$request->id, 'document_date' => now(), 'supplier_or_recipient' => 'Civic Supply Co.', 'status' => 'prepared']);
        $item = $request->items()->first();
        ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id, 'document_type' => 'inspection_acceptance_report', 'document_number' => 'IAR-'.$request->id, 'document_date' => now(), 'status' => 'prepared', 'metadata' => ['received_items' => [$item->id => $received]]]);
    }
}
