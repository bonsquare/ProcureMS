<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
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
        $this->actingAs($u)->get(route('procurement.create'))->assertOk()->assertSee('aria-label="Procurement areas"', false)->assertSee('Request details')->assertSee('Funding and linkage')->assertSee('Items')->assertSee('aria-live="polite"', false)->assertSee('Save request');
    }
}
