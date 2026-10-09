<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementOfficialToolbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_previews_use_an_accessible_screen_only_toolbar_with_context(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'Sample School']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $request = ProcurementRequest::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id,
            'request_number' => 'PR-2026-010', 'title' => 'Civic supplies', 'amount' => 100, 'status' => 'approved',
        ]);
        $document = ProcurementDocument::create([
            'organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id,
            'document_type' => 'purchase_order', 'document_number' => 'PO-2026-010', 'document_date' => now(), 'status' => 'prepared',
            'metadata' => ['delivery_schedule' => ''],
        ]);

        foreach ([route('procurement.print', $request), route('procurement.documents.print', [$request, $document])] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('role="toolbar"', false)
                ->assertSee('aria-label="Official document actions"', false)
                ->assertSee('Paper Size')->assertSee('Orientation')->assertSee('Print / Save as PDF')
                ->assertSee('PR-2026-010')
                ->assertSee('.official-toolbar, .no-print { display: none !important; }', false);
        }
    }

    public function test_every_document_prints_even_when_no_details_were_saved(): void
    {
        $organization = Organization::create(['name' => 'Bare Org', 'slug' => 'bare-org']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'BARE', 'name' => 'Bare School']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $request = ProcurementRequest::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id,
            'request_number' => 'PR-2026-BARE', 'title' => 'Seeded demo request', 'amount' => 100, 'status' => 'completed',
        ]);

        foreach (array_values(['request_for_quotation', 'abstract_of_bids_quotation', 'notice_to_award', 'purchase_order', 'notice_to_proceed', 'inspection_acceptance_report', 'requisition_issuance_slip', 'inventory_custodian_slip', 'inventory_acknowledgement_receipt_supplies']) as $index => $type) {
            $document = ProcurementDocument::create([
                'organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id,
                'document_type' => $type, 'document_number' => sprintf('DOC-2026-%03d', $index + 1), 'document_date' => now(), 'status' => 'prepared', 'metadata' => null,
            ]);

            $this->actingAs($user)->get(route('procurement.documents.print', [$request, $document]))->assertOk();
        }
    }
}
