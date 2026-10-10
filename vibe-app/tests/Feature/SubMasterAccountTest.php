<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubMasterAccountTest extends TestCase
{
    use RefreshDatabase;

    private function sub(array $access = []): User
    {
        return User::factory()->subMaster([...SubMasterAccess::defaults(), ...$access])->create([
            'name' => 'Sub Helper', 'username' => 'sub.helper', 'email' => 'sub@example.com', 'phone' => '0900', 'position' => 'Helper',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Changed Name', 'username' => 'changed.name', 'email' => 'changed@example.com', 'phone' => '09171234567', 'position' => 'Senior helper'];
    }

    public function test_a_sub_master_edits_their_own_email_phone_position_and_password(): void
    {
        $sub = $this->sub();

        $this->actingAs($sub)->put(route('master-user.update'), $this->payload())
            ->assertRedirect(route('user-management', ['tab' => 'master-user']))->assertSessionHas('success');
        $fresh = $sub->fresh();
        $this->assertSame(['changed@example.com', '09171234567', 'Senior helper'], [$fresh->email, $fresh->phone, $fresh->position]);

        $this->actingAs($fresh)->put(route('master-user.password'), ['current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('a-Strong-Passphrase-1', $sub->fresh()->password));
    }

    public function test_name_and_username_are_ignored_unless_edit_own_name_is_on(): void
    {
        $off = $this->sub();
        $this->actingAs($off)->put(route('master-user.update'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(['Sub Helper', 'sub.helper'], [$off->fresh()->name, $off->fresh()->username]);

        $on = User::factory()->subMaster([...SubMasterAccess::defaults(), 'edit_own_name'])->create(['name' => 'Other Helper', 'username' => 'other.helper']);
        $this->actingAs($on)->put(route('master-user.update'), $this->payload(['email' => 'on@example.com']))->assertSessionHasNoErrors();
        $this->assertSame(['Changed Name', 'changed.name'], [$on->fresh()->name, $on->fresh()->username]);
    }

    public function test_extra_role_access_and_status_fields_change_nothing(): void
    {
        $sub = $this->sub();
        $access = $sub->access;

        $this->actingAs($sub)->put(route('master-user.update'), $this->payload(['role' => 'master_user', 'access' => ['delete'], 'status' => 'inactive', 'school_id' => 1, 'organization_id' => 1]))
            ->assertSessionHasNoErrors();

        $fresh = $sub->fresh();
        $this->assertSame(['sub_master', 'active', null, null, $access], [$fresh->role, $fresh->status, $fresh->school_id, $fresh->organization_id, $fresh->access]);
    }

    public function test_the_tab_shows_a_sub_master_only_their_own_account(): void
    {
        $this->actingAs($this->sub())->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('Account details')->assertSee('Change password')->assertDontSee('Add Sub-master')->assertDontSee('Reset password');
    }

    public function test_name_and_username_are_read_only_on_screen_without_edit_own_name(): void
    {
        $this->actingAs($this->sub())->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('name="name"', false)->assertSee('readonly', false);
    }

    public function test_the_master_still_edits_their_own_name_and_username(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'name' => 'Old', 'username' => 'old.one']);

        $this->actingAs($master)->put(route('master-user.update'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(['Changed Name', 'changed.name'], [$master->fresh()->name, $master->fresh()->username]);
    }

    public function test_a_school_admin_still_cannot_use_the_own_account_forms(): void
    {
        $admin = User::factory()->create(['role' => 'school_admin']);

        $this->actingAs($admin)->put(route('master-user.update'), $this->payload())->assertForbidden();
        $this->actingAs($admin)->put(route('master-user.password'), ['current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertForbidden();
    }

    public function test_a_deactivated_sub_master_cannot_sign_in(): void
    {
        $sub = $this->sub();
        $sub->forceFill(['status' => 'inactive'])->save();

        $this->post(route('login.store'), ['email' => 'sub@example.com', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_an_open_session_of_a_deactivated_sub_master_ends_on_the_next_request(): void
    {
        $sub = $this->sub();
        $this->actingAs($sub)->get(route('home'))->assertOk();

        $sub->forceFill(['status' => 'inactive'])->save();

        $this->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_master_is_never_signed_out_by_a_status_value(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'status' => 'inactive']);

        $this->actingAs($master)->get(route('home'))->assertOk();
    }

    public function test_a_school_user_cannot_be_given_the_sub_master_role(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $master = User::factory()->create(['role' => 'master_user']);

        foreach ([$admin, $master] as $actor) {
            $this->actingAs($actor)->put(route('school-settings.users.update', $viewer), [
                'school_id' => $school->id, 'name' => $viewer->name, 'email' => $viewer->email, 'role' => 'sub_master', 'status' => 'active',
            ]);
            $this->assertSame('viewer', $viewer->fresh()->role);
        }
    }
}
