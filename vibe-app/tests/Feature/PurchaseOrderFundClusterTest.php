<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderFundClusterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_purchase_order_prints_its_fund_cluster_and_defaults_to_general_fund(): void
    {
        $organization = Organization::create(['name' => 'Fc Org', 'slug' => 'fc-org']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'FCS', 'name' => 'Fund School']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $request = ProcurementRequest::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id,
            'request_number' => 'PR-2026-FC', 'title' => 'Supplies', 'amount' => 100, 'status' => 'approved',
        ]);
        $typed = ProcurementDocument::create([
            'organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id,
            'document_type' => 'purchase_order', 'document_number' => 'PO-2026-001', 'document_date' => now(), 'status' => 'prepared',
            'metadata' => ['fund_cluster' => 'Special Education Fund', 'source_of_fund' => 'MOOE'],
        ]);

        $this->actingAs($user)->get(route('procurement.documents.print', [$request, $typed]))
            ->assertOk()->assertSee('Fund Cluster : <strong>Special Education Fund</strong>', false);

        $typed->update(['metadata' => ['source_of_fund' => 'MOOE']]);

        $this->actingAs($user)->get(route('procurement.documents.print', [$request, $typed]))
            ->assertOk()->assertSee('Fund Cluster : <strong>General Fund</strong>', false);
    }
}
