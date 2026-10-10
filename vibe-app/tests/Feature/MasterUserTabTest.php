<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterUserTabTest extends TestCase
{
    use RefreshDatabase;

    private function master(array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'role' => 'master_user', 'name' => 'Old Master', 'username' => 'old.master', 'email' => 'master@example.com', 'status' => 'active',
        ]);
    }

    private function schoolAdmin(): User
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function details(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Master Name', 'username' => 'new.master', 'email' => 'new-master@example.com',
            'phone' => '09171234567', 'position' => 'System Owner',
        ];
    }

    public function test_the_master_user_tab_shows_the_account_details_and_the_password_form(): void
    {
        $this->actingAs($this->master())->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('Master User')->assertSee('value="Old Master"', false)->assertSee('value="old.master"', false)
            ->assertSee('Change password')->assertSee('Current password');
    }

    public function test_other_roles_cannot_reach_the_tab_or_its_forms(): void
    {
        $admin = $this->schoolAdmin();

        $this->actingAs($admin)->get(route('user-management', ['tab' => 'master-user']))->assertForbidden();
        $this->actingAs($admin)->put(route('master-user.update'), $this->details())->assertForbidden();
        $this->actingAs($admin)->put(route('master-user.password'), ['current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertForbidden();
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_the_master_changes_the_account_details_including_name_and_username(): void
    {
        $master = $this->master();

        $this->actingAs($master)->put(route('master-user.update'), $this->details())
            ->assertRedirect(route('user-management', ['tab' => 'master-user']))->assertSessionHas('success');

        $fresh = $master->fresh();
        $this->assertSame(['New Master Name', 'new.master', 'new-master@example.com', '09171234567', 'System Owner'], [$fresh->name, $fresh->username, $fresh->email, $fresh->phone, $fresh->position]);
        $this->assertSame(1, AuditLog::where(['user_id' => $master->id, 'action' => 'master_profile_updated'])->count());
    }

    public function test_keeping_my_own_email_and_username_is_not_a_duplicate(): void
    {
        $master = $this->master();

        $this->actingAs($master)->put(route('master-user.update'), $this->details(['username' => 'old.master', 'email' => 'master@example.com']))
            ->assertSessionHasNoErrors();
    }

    public function test_an_email_or_username_used_by_someone_else_is_refused(): void
    {
        $master = $this->master();
        User::factory()->create(['email' => 'taken@example.com', 'username' => 'taken.name']);

        $this->actingAs($master)->put(route('master-user.update'), $this->details(['email' => 'taken@example.com', 'username' => 'taken.name']))
            ->assertSessionHasErrors(['email', 'username']);

        $this->assertSame('master@example.com', $master->fresh()->email);
    }

    public function test_a_username_with_spaces_or_symbols_is_refused(): void
    {
        $this->actingAs($this->master())->put(route('master-user.update'), $this->details(['username' => 'bad name!']))
            ->assertSessionHasErrors('username');
    }

    public function test_the_role_and_status_cannot_be_changed_from_this_form(): void
    {
        $master = $this->master();

        $this->actingAs($master)->put(route('master-user.update'), $this->details(['role' => 'viewer', 'status' => 'inactive']))->assertSessionHasNoErrors();

        $this->assertSame(['master_user', 'active'], [$master->fresh()->role, $master->fresh()->status]);
    }

    public function test_the_master_changes_the_password_with_the_current_one(): void
    {
        $master = $this->master();

        $this->actingAs($master)->put(route('master-user.password'), [
            'current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1',
        ])->assertRedirect(route('user-management', ['tab' => 'master-user']))->assertSessionHas('success');

        $this->assertTrue(Hash::check('a-Strong-Passphrase-1', $master->fresh()->password));
        $this->assertNotNull($master->fresh()->password_changed_at);
        $this->assertSame(1, AuditLog::where(['user_id' => $master->id, 'action' => 'master_password_changed'])->count());
    }

    public function test_a_wrong_current_password_a_short_one_or_a_mismatch_changes_nothing(): void
    {
        $master = $this->master();
        $url = route('master-user.password');

        $this->actingAs($master)->put($url, ['current_password' => 'wrong', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertSessionHasErrors('current_password');
        $this->actingAs($master)->put($url, ['current_password' => 'password', 'password' => 'short-pass1', 'password_confirmation' => 'short-pass1'])->assertSessionHasErrors('password');
        $this->actingAs($master)->put($url, ['current_password' => 'password', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'different-Passphrase-2'])->assertSessionHasErrors('password');
        $this->actingAs($master)->put($url, ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $master->fresh()->password));
    }
}
