<?php

namespace Tests\Feature;

use App\Models\SchoolStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The School Settings save routes for staff and the organization defaults. */
class SchoolSettingsStaffRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function defaults(array $overrides = []): array
    {
        return $overrides + ['organization_code' => 'orgx', 'fiscal_year' => 2026, 'default_fund_source' => 'MOOE', 'pr_prefix' => 'pr', 'ors_prefix' => 'ors', 'dv_prefix' => 'dv'];
    }

    public function test_staff_are_added_and_their_roles_saved_for_the_own_school(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('school-settings.staff.add'), ['school_id' => $fx['school']->id, 'new_staff' => [['name' => 'Ana Reyes', 'position' => 'Teacher III', 'bac_role' => 'BAC Member']]])->assertSessionHasNoErrors();
        $staff = SchoolStaff::where('name', 'Ana Reyes')->firstOrFail();
        $this->assertSame($fx['school']->id, $staff->school_id);

        $this->post(route('school-settings.staff'), ['school_id' => $fx['school']->id, 'staff' => [$staff->id => ['position' => 'Master Teacher I']]])->assertSessionHasNoErrors();
        $this->assertSame('Master Teacher I', $staff->fresh()->position);
    }

    public function test_a_new_staff_member_needs_a_name(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->post(route('school-settings.staff.add'), ['school_id' => $fx['school']->id, 'new_staff' => [['name' => '', 'position' => 'Teacher']]])->assertSessionHasErrors('new_staff.0.name');
        $this->post(route('school-settings.staff.add'), ['school_id' => $fx['school']->id])->assertSessionHasErrors('new_staff');
        $this->assertSame(0, SchoolStaff::count());
    }

    public function test_another_schools_staff_cannot_be_added_or_changed(): void
    {
        $mine = SobFixture::make();
        $other = SobFixture::make();
        $theirs = SchoolStaff::create(['school_id' => $other['school']->id, 'name' => 'Their Teacher', 'position' => 'Teacher I']);

        $this->actingAs($mine['user'])->post(route('school-settings.staff.add'), ['school_id' => $other['school']->id, 'new_staff' => [['name' => 'Intruder']]])->assertForbidden();
        $this->post(route('school-settings.staff'), ['school_id' => $other['school']->id, 'staff' => [$theirs->id => ['position' => 'Hacked']]])->assertForbidden();

        $this->assertSame(0, SchoolStaff::where('name', 'Intruder')->count());
        $this->assertSame('Teacher I', $theirs->fresh()->position);
    }

    public function test_only_roles_with_organization_settings_may_save_staff_and_defaults(): void
    {
        $fx = SobFixture::make();
        $viewer = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->post(route('school-settings.staff.add'), ['school_id' => $fx['school']->id, 'new_staff' => [['name' => 'Ana']]])->assertForbidden();
        $this->post(route('school-settings.staff'), ['school_id' => $fx['school']->id, 'staff' => []])->assertForbidden();
        $this->post(route('school-settings.organization'), $this->defaults())->assertForbidden();
    }

    public function test_the_organization_defaults_and_numbering_prefixes_are_saved_in_upper_case(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->post(route('school-settings.organization'), $this->defaults())->assertSessionHasNoErrors();

        $organization = $fx['organization']->fresh();
        $this->assertSame('ORGX', $organization->organization_code);
        $this->assertSame('MOOE', $organization->default_fund_source);
        $this->assertSame(['purchase_request' => 'PR', 'obligation_request' => 'ORS', 'disbursement_voucher' => 'DV'], $organization->numbering_preferences);
    }

    public function test_the_organization_defaults_reject_a_bad_prefix_year_and_a_code_in_use(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $other['organization']->update(['organization_code' => 'TAKEN']);
        $this->actingAs($fx['user']);

        $this->post(route('school-settings.organization'), $this->defaults(['pr_prefix' => 'a b']))->assertSessionHasErrors('pr_prefix');
        $this->post(route('school-settings.organization'), $this->defaults(['fiscal_year' => 1999]))->assertSessionHasErrors('fiscal_year');
        $this->post(route('school-settings.organization'), $this->defaults(['organization_code' => 'taken']))->assertSessionHasErrors('organization_code');
        $this->assertNotSame('TAKEN', $fx['organization']->fresh()->organization_code);
    }
}
