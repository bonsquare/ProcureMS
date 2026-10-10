<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_abstract_preview_renders_while_no_company_is_chosen_yet(): void
    {
        $organization = Organization::create(['name' => 'Abs', 'slug' => 'abs', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ABS', 'name' => 'Abstract School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-ABS', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);
        $pr->items()->create(['organization_id' => $organization->id, 'name' => 'RICE', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 1500, 'total' => 1500]);

        // What the dialog posts before any company is picked: three empty bidder slots.
        $this->actingAs($user)->post(route('procurement.documents.preview', $pr), [
            'document_type' => 'abstract_of_bids_quotation', 'document_date' => '2026-10-10', 'document_number' => 'ABQ-2026-001',
            'bidders' => [['name' => '', 'prices' => []], ['name' => '', 'prices' => []], ['name' => '', 'prices' => []]],
        ])->assertOk();
    }
}
