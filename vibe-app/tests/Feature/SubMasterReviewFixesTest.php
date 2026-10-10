<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Findings of the final review of the Sub-master work, each pinned by a test that failed first. */
class SubMasterReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function school(): array
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $organization->id, 'school_id' => $school->id, 'status' => 'active']);

        return [$organization, $school, $viewer];
    }

    private function sub(array $extra = [], array $without = []): User
    {
        $access = array_values(array_diff([...SubMasterAccess::defaults(), ...$extra], $without));

        return User::factory()->subMaster($access)->create();
    }

    private function refusals(User $user): int
    {
        return AuditLog::where(['user_id' => $user->id, 'action' => 'sub_master_action_refused'])->count();
    }

    public function test_a_sub_master_cannot_reset_the_masters_or_another_sub_masters_password_through_school_settings(): void
    {
        [, $school] = $this->school();
        $master = User::factory()->create(['role' => 'master_user']);
        $other = $this->sub();
        $actor = $this->sub();

        foreach ([$master, $other] as $target) {
            $this->actingAs($actor)->put(route('school-settings.users.password', $target), ['school_id' => $school->id, 'password' => 'takeover-pass-1', 'password_confirmation' => 'takeover-pass-1'])
                ->assertForbidden();
            $this->assertTrue(Hash::check('password', $target->fresh()->password));
        }
    }

    public function test_a_sub_master_cannot_demote_or_deactivate_a_master_through_school_settings(): void
    {
        [, $school] = $this->school();
        $master = User::factory()->create(['role' => 'master_user', 'status' => 'active']);
        $other = $this->sub();

        foreach ([$master, $other] as $target) {
            $this->actingAs($this->sub(['deactivate_user']))->put(route('school-settings.users.update', $target), [
                'school_id' => $school->id, 'email' => $target->email, 'role' => 'viewer', 'status' => 'inactive',
            ])->assertForbidden();
            $this->assertSame([$target->role, 'active'], [$target->fresh()->role, $target->fresh()->status]);
        }
    }

    public function test_deactivating_a_school_user_needs_the_deactivate_switch_everywhere(): void
    {
        [, $school, $viewer] = $this->school();
        $without = $this->sub();
        $with = $this->sub(['deactivate_user']);
        $payload = ['school_id' => $school->id, 'email' => $viewer->email, 'role' => 'viewer', 'status' => 'inactive'];

        $this->actingAs($without)->put(route('school-settings.users.update', $viewer), $payload)->assertForbidden();
        $this->assertSame('active', $viewer->fresh()->status);
        $this->assertSame(1, $this->refusals($without));

        $this->actingAs($with)->put(route('school-settings.users.update', $viewer), $payload)->assertSessionHasNoErrors();
        $this->assertSame('inactive', $viewer->fresh()->status);

        $this->actingAs($without)->put(route('school-settings.users.update', $viewer), ['status' => 'active'] + $payload)->assertSessionHasNoErrors();
    }

    public function test_ending_a_handover_and_closing_a_school_need_the_deactivate_switch(): void
    {
        [, $school] = $this->school();
        $without = $this->sub();

        $this->actingAs($without)->post(route('school-management.handover.end', 1))->assertForbidden();
        $this->actingAs($without)->post(route('school-management.status', $school), ['active' => 0])->assertForbidden();
        $this->actingAs($without)->post(route('school-settings.school'), ['school_id' => $school->id, 'name' => 'School', 'status' => 'inactive'])->assertForbidden();
        $this->assertSame(3, $this->refusals($without));
        $this->assertSame('active', $school->fresh()->status);

        $this->actingAs($without)->post(route('school-management.status', $school), ['active' => 1])->assertRedirect();
    }

    public function test_a_school_status_in_school_details_needs_the_schools_access(): void
    {
        [, $school] = $this->school();
        $sub = $this->sub(['deactivate_user'], ['schools']);

        $this->actingAs($sub)->post(route('school-settings.school'), ['school_id' => $school->id, 'name' => 'School', 'status' => 'inactive'])->assertSessionHasNoErrors();

        $this->assertSame('active', $school->fresh()->status);
    }

    public function test_closing_a_fiscal_year_needs_the_close_budget_switch(): void
    {
        [, $school] = $this->school();
        $without = $this->sub();
        $with = $this->sub(['close_budget']);
        $payload = ['school_id' => $school->id, 'year' => 2026, 'status' => 'closed'];

        $this->actingAs($without)->post(route('planning.fiscal-year.status'), $payload)->assertForbidden();
        $this->assertSame(1, $this->refusals($without));

        $this->actingAs($with)->post(route('planning.fiscal-year.status'), $payload);
        $this->assertSame(0, $this->refusals($with));

        $this->actingAs($without)->post(route('planning.fiscal-year.status'), ['status' => 'open'] + $payload);
        $this->assertSame(1, $this->refusals($without));
    }

    public function test_a_sub_master_with_no_area_on_sees_nothing_but_keeps_their_own_account(): void
    {
        [, $school] = $this->school();
        Aip::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026]);
        $none = User::factory()->subMaster([])->create();
        $onlySwitches = User::factory()->subMaster(['delete', 'close_budget'])->create();

        foreach ([$none, $onlySwitches] as $user) {
            $this->assertFalse($user->seesAllSchools());
            $this->actingAs($user);
            $this->assertSame(0, Aip::count());
        }

        $this->actingAs($none)->get(route('user-management', ['tab' => 'master-user']))->assertOk()->assertSee('Change password');
        $this->actingAs($none)->put(route('master-user.update'), ['email' => 'none@example.com', 'phone' => '1', 'position' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame('none@example.com', $none->fresh()->email);
        $this->actingAs($none)->put(route('master-user.password'), ['current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertSessionHas('success');
    }

    public function test_the_whole_audit_log_export_needs_the_users_access_for_a_sub_master(): void
    {
        $this->actingAs($this->sub([], ['users']))->get(route('dashboard.audit-logs.export'))->assertForbidden();
        $this->actingAs($this->sub())->get(route('dashboard.audit-logs.export'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'master_user']))->get(route('dashboard.audit-logs.export'))->assertOk();
    }

    public function test_the_account_menu_links_a_sub_master_to_their_own_account(): void
    {
        $this->actingAs($this->sub())->get(route('home'))->assertOk()->assertSee('href="'.route('user-management').'"', false);
    }
}
