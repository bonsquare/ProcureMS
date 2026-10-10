<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StaffRoleOption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffStatusAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$organization, $school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin'])];
    }

    private function page(School $school): string
    {
        return route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'staff']);
    }

    public function test_a_new_employee_is_active_and_can_be_added_as_inactive(): void
    {
        [, $school, $user] = $this->tenant('staff-a');

        $this->actingAs($user)->post(route('school-settings.employees.store'), ['school_id' => $school->id, 'name' => 'Ana Active'])->assertRedirect();
        $this->post(route('school-settings.employees.store'), ['school_id' => $school->id, 'name' => 'Ivo Inactive', 'is_active' => '0'])->assertRedirect();

        $this->assertTrue(SchoolStaff::where('name', 'Ana Active')->firstOrFail()->is_active);
        $this->assertFalse(SchoolStaff::where('name', 'Ivo Inactive')->firstOrFail()->is_active);
    }

    public function test_an_employee_can_be_set_inactive_and_active_again_and_stays_in_the_list(): void
    {
        [, $school, $user] = $this->tenant('staff-b');
        $staff = SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Ben Staff']);

        $this->actingAs($user)->put(route('school-settings.employees.update', $staff), ['school_id' => $school->id, 'name' => 'Ben Staff', 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);
        $this->get($this->page($school))->assertOk()->assertSee('Ben Staff')->assertSee('Inactive');

        $this->put(route('school-settings.employees.update', $staff), ['school_id' => $school->id, 'name' => 'Ben Staff', 'is_active' => '1'])->assertRedirect();
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_inactive_employees_are_left_out_of_the_signatory_lookups(): void
    {
        [, $school] = $this->tenant('staff-c');
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'On Duty', 'document_role' => 'Inspection Officer']);
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Off Duty', 'document_role' => 'Inspection Officer', 'is_active' => false]);

        $this->assertSame(['On Duty'], SchoolStaff::active()->pluck('name')->all());
        $this->assertSame(2, SchoolStaff::count());
    }

    public function test_the_status_is_validated_and_other_schools_cannot_be_changed(): void
    {
        [, $school, $user] = $this->tenant('staff-d');
        [, $otherSchool] = $this->tenant('staff-e');
        $other = SchoolStaff::create(['organization_id' => $otherSchool->organization_id, 'school_id' => $otherSchool->id, 'name' => 'Not Yours']);

        $this->actingAs($user)->post(route('school-settings.employees.store'), ['school_id' => $school->id, 'name' => 'Bad', 'is_active' => 'maybe'])->assertSessionHasErrors('is_active');
        $this->put(route('school-settings.employees.update', $other), ['school_id' => $otherSchool->id, 'name' => 'Not Yours', 'is_active' => '0'])->assertStatus(404);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_a_role_can_be_added_without_leaving_the_form(): void
    {
        [$organization, $school, $user] = $this->tenant('staff-f');

        $this->actingAs($user)->postJson(route('school-settings.roles.store'), ['school_id' => $school->id, 'role_group' => 'document_role', 'name' => 'Records Custodian'])
            ->assertOk()->assertJson(['role_group' => 'document_role', 'name' => 'Records Custodian', 'added' => true]);
        $this->assertDatabaseHas('staff_role_options', ['organization_id' => $organization->id, 'role_group' => 'document_role', 'name' => 'Records Custodian']);

        $this->postJson(route('school-settings.roles.store'), ['school_id' => $school->id, 'role_group' => 'bac_role', 'name' => 'bac member'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame(1, StaffRoleOption::count());
    }

    public function test_both_dialogs_offer_the_missing_role_box_and_the_update_button(): void
    {
        [, $school, $user] = $this->tenant('staff-g');

        $html = $this->actingAs($user)->get($this->page($school))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'Need a role that is not listed?'));
        $this->assertStringContainsString('Update employee', $html);
        $this->assertStringNotContainsString('Save your changes first', $html);
    }
}
