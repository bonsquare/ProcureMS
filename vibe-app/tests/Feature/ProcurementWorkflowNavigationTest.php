<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Database\Seeders\CompleteWorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWorkflowNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_workflow_has_predictable_links_across_procurement_and_transactions(): void
    {
        $organization = Organization::create(['name' => 'Test School', 'slug' => 'test-school', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH-TEST', 'name' => 'Test School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        app(CompleteWorkflowDemoSeeder::class)->run();
        $request = ProcurementRequest::where('request_number', 'DEMO-PR-2026-005')->firstOrFail();
        $purchaseOrder = $request->documents()->where('document_type', 'purchase_order')->firstOrFail();

        $this->actingAs($user)->get(route('procurement'))
            ->assertOk()->assertSee(route('procurement.requests'), false);
        $this->get(route('procurement.requests'))
            ->assertOk()->assertSee(route('procurement.show', $request), false);
        $this->get(route('procurement.show', $request))
            ->assertOk()->assertSee(route('procurement.documents', $request), false)
            ->assertSee(route('transactions.show', $request->transaction), false)
            ->assertSee('Skip to main content')
            ->assertSee('aria-label="Procurement areas"', false);
        $this->get(route('procurement.documents', $request))
            ->assertOk()->assertSee(route('procurement.documents.print', [$request, $purchaseOrder]), false)
            ->assertSee('aria-label="Close document form"', false);
        $this->get(route('procurement.receiving'))
            ->assertOk()->assertSee(route('procurement.delivery-reconciliation', $request), false);
        $this->get(route('transactions.show', $request->transaction))
            ->assertOk()->assertSee(route('procurement.show', $request), false)
            ->assertSee('Open Procurement workspace');
        $this->assertStringContainsString(':focus-visible', file_get_contents(public_path('css/procurement.css')));
    }
}
