<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_workspace_sections_labels_and_live_item_feedback(): void
    {
        $o = Organization::create(['name' => 'Form Org', 'slug' => 'form-org', 'status' => 'active']);
        $s = School::create(['organization_id' => $o->id, 'code' => 'FORM', 'name' => 'Form School', 'status' => 'active']);
        $u = User::factory()->create(['organization_id' => $o->id, 'school_id' => $s->id, 'role' => 'school_admin']);
        $this->actingAs($u)->get(route('procurement.create'))->assertOk()->assertSee('aria-label="Main navigation"', false)->assertSee('aria-label="Procurement areas"', false)->assertSee('aria-current="page"', false)->assertSee('Request details')->assertSee('Funding and linkage')->assertSee('Items')->assertSee('aria-live="polite"', false)->assertSee('Save request');
    }

    public function test_edit_form_previews_the_existing_request_without_spoofing_put(): void
    {
        $o = Organization::create(['name' => 'Edit Org', 'slug' => 'edit-org', 'status' => 'active']);
        $s = School::create(['organization_id' => $o->id, 'code' => 'EDIT', 'name' => 'Edit School', 'status' => 'active']);
        Subscription::create(['organization_id' => $o->id, 'school_id' => $s->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $u = User::factory()->create(['organization_id' => $o->id, 'school_id' => $s->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $o->id, 'school_id' => $s->id, 'requested_by' => $u->id, 'request_number' => 'PR-2026-EDIT', 'title' => 'Old title', 'amount' => 100, 'status' => 'submitted']);

        $this->actingAs($u)->get(route('procurement.edit', $pr))->assertOk()->assertSee("body.delete('_method')", false);
        $this->post(route('procurement.preview'), ['editing_id' => $pr->id, 'school_id' => $s->id, 'purpose' => 'New title', 'items' => [['name' => 'Pen', 'unit' => 'box', 'quantity' => 2, 'unit_price' => 10]]])
            ->assertOk()->assertSee('PR-2026-EDIT')->assertSee('New title')->assertSee('Pen');
    }
}
