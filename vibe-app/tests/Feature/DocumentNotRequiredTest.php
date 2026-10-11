<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNotRequiredTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'nr', string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        $user = User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-'.strtoupper($slug), 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);

        return [$user, $request];
    }

    private function document(ProcurementRequest $request, User $user, string $type): ProcurementDocument
    {
        return ProcurementDocument::create([
            'organization_id' => $request->organization_id, 'procurement_request_id' => $request->id, 'created_by' => $user->id, 'document_type' => $type,
            'document_number' => strtoupper(substr($type, 0, 3)).'-2026-001', 'document_date' => '2026-10-11', 'status' => 'prepared',
        ]);
    }

    public function test_a_user_marks_an_optional_document_not_required_and_makes_it_required_again(): void
    {
        [$user, $request] = $this->tenant();

        $this->actingAs($user)->post(route('procurement.documents.not-required', $request), ['document_type' => 'inventory_custodian_slip'])->assertSessionHasNoErrors();

        $mark = $request->fresh()->not_required_documents['inventory_custodian_slip'];
        $this->assertSame($user->id, $mark['user_id']);
        $this->assertSame($user->name, $mark['name']);
        $this->assertNotEmpty($mark['at']);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'document_marked_not_required')->where('auditable_id', $request->id)->exists());

        $this->post(route('procurement.documents.required-again', $request), ['document_type' => 'inventory_custodian_slip'])->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('inventory_custodian_slip', $request->fresh()->not_required_documents ?? []);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'document_required_again')->where('auditable_id', $request->id)->exists());
    }

    public function test_only_the_optional_documents_accept_it(): void
    {
        [$user, $request] = $this->tenant();

        foreach (['purchase_order', 'requisition_issuance_slip', 'made_up', ''] as $type) {
            $this->actingAs($user)->post(route('procurement.documents.not-required', $request), ['document_type' => $type])->assertSessionHasErrors('document_type');
        }

        $this->assertNull($request->fresh()->not_required_documents);
    }

    public function test_a_prepared_document_cannot_be_marked_not_required(): void
    {
        [$user, $request] = $this->tenant();
        $this->document($request, $user, 'property_acknowledgement_receipt');

        $this->actingAs($user)->post(route('procurement.documents.not-required', $request), ['document_type' => 'property_acknowledgement_receipt'])->assertStatus(422);

        $this->assertNull($request->fresh()->not_required_documents);
    }

    public function test_a_user_who_cannot_edit_documents_is_refused(): void
    {
        [$viewer, $request] = $this->tenant('view', 'viewer');

        $this->actingAs($viewer)->post(route('procurement.documents.not-required', $request), ['document_type' => 'inventory_custodian_slip'])->assertForbidden();
        $this->post(route('procurement.documents.required-again', $request), ['document_type' => 'inventory_custodian_slip'])->assertForbidden();
    }

    public function test_another_organizations_request_is_refused(): void
    {
        [, $request] = $this->tenant('one');
        [$stranger] = $this->tenant('two');

        $response = $this->actingAs($stranger)->post(route('procurement.documents.not-required', $request), ['document_type' => 'inventory_custodian_slip']);

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNull($request->fresh()->not_required_documents);
    }

    public function test_saving_the_document_clears_the_mark(): void
    {
        [$user, $request] = $this->tenant();
        $this->document($request, $user, 'requisition_issuance_slip');
        $this->actingAs($user)->post(route('procurement.documents.not-required', $request), ['document_type' => 'property_acknowledgement_receipt'])->assertSessionHasNoErrors();

        $this->post(route('procurement.documents.store', $request), ['document_type' => 'property_acknowledgement_receipt', 'document_date' => '2026-10-11'])->assertSessionHasNoErrors();

        $this->assertTrue($request->documents()->where('document_type', 'property_acknowledgement_receipt')->exists());
        $this->assertArrayNotHasKey('property_acknowledgement_receipt', $request->fresh()->not_required_documents ?? []);
    }

    public function test_the_mark_does_not_change_progress_or_the_missing_list(): void
    {
        [$user, $request] = $this->tenant();
        $service = app(ProcurementWorkspaceService::class);
        $before = $service->present($request->fresh()->load('documents'));

        $this->actingAs($user)->post(route('procurement.documents.not-required', $request), ['document_type' => 'inventory_custodian_slip']);
        $this->post(route('procurement.documents.not-required', $request), ['document_type' => 'property_acknowledgement_receipt']);

        $this->assertSame($before, $service->present($request->fresh()->load('documents')));
    }
}
