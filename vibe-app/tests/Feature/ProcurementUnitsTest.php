<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementUnitsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);
    }

    public function test_a_unit_can_be_added_and_appears_in_the_pr_item_dropdown(): void
    {
        $user = $this->tenant('units-a');

        $this->actingAs($user)->post(route('units.store'), ['name' => '  bottle '])->assertRedirect(route('units.index'));

        $this->assertDatabaseHas('units', ['name' => 'bottle', 'organization_id' => $user->organization_id]);
        $this->get(route('units.index'))->assertOk()->assertSee('bottle')->assertSee('Built-in');
        $this->get(route('procurement.create'))->assertOk()->assertSee('"bottle"', false);
    }

    public function test_duplicate_units_are_rejected_including_built_in_ones(): void
    {
        $user = $this->tenant('units-b');

        $this->actingAs($user)->post(route('units.store'), ['name' => 'Piece'])->assertSessionHasErrors('name');
        $this->post(route('units.store'), ['name' => 'roll']);
        $this->post(route('units.store'), ['name' => 'ROLL'])->assertSessionHasErrors('name');
        $this->assertSame(1, Unit::where('name', 'roll')->count());
    }

    public function test_units_are_isolated_between_organizations(): void
    {
        $first = $this->tenant('units-c');
        $second = $this->tenant('units-d');
        $this->actingAs($first)->post(route('units.store'), ['name' => 'carboy']);
        $unit = Unit::where('name', 'carboy')->firstOrFail();

        $this->flushSession();
        $this->actingAs($second)->get(route('units.index'))->assertOk()->assertDontSee('carboy');
        $this->delete(route('units.destroy', $unit))->assertNotFound();
        $this->assertDatabaseHas('units', ['name' => 'carboy']);

        $this->actingAs($first)->delete(route('units.destroy', $unit))->assertRedirect(route('units.index'));
        $this->assertDatabaseMissing('units', ['name' => 'carboy']);
    }

    public function test_a_viewer_cannot_add_units(): void
    {
        $viewer = $this->tenant('units-e', 'auditor');

        $this->actingAs($viewer)->post(route('units.store'), ['name' => 'carton'])->assertForbidden();
        $this->assertDatabaseMissing('units', ['name' => 'carton']);
    }
}
