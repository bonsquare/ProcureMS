<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'name' => 'Real Master']);
    }

    private function newAccount(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Helper Admin', 'username' => 'helper.admin', 'email' => 'helper@example.com', 'phone' => '09170000000',
            'position' => 'Co-admin', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1',
        ];
    }

    private function details(User $sub, array $overrides = []): array
    {
        return $overrides + ['name' => $sub->name, 'username' => $sub->username, 'email' => $sub->email, 'phone' => '09171112222', 'position' => 'Updated'];
    }

    public function test_the_master_adds_a_sub_master_with_the_default_checklist(): void
    {
        $this->actingAs($this->master())->post(route('master-user.sub-masters.store'), $this->newAccount())
            ->assertRedirect(route('user-management', ['tab' => 'master-user']))->assertSessionHas('success');

        $sub = User::where('email', 'helper@example.com')->firstOrFail();
        $this->assertSame(['sub_master', 'active', null, null], [$sub->role, $sub->status, $sub->school_id, $sub->organization_id]);
        $this->assertSame(SubMasterAccess::defaults(), $sub->access);
        $this->assertTrue(Hash::check('a-Strong-Passphrase-1', $sub->password));
        $this->assertSame(1, AuditLog::where('action', 'sub_master_created')->count());
    }

    public function test_the_checklist_is_saved_and_changes_are_audited(): void
    {
        $master = $this->master();
        $this->actingAs($master)->post(route('master-user.sub-masters.store'), $this->newAccount(['access_present' => 1, 'access' => ['schools', 'delete']]));
        $sub = User::where('email', 'helper@example.com')->firstOrFail();
        $this->assertSame(['schools', 'delete'], $sub->access);

        $this->actingAs($master)->put(route('master-user.sub-masters.update', $sub), $this->details($sub, ['access_present' => 1, 'access' => ['backup']]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['backup'], $sub->fresh()->access);
        $this->assertSame('09171112222', $sub->fresh()->phone);
        $log = AuditLog::where('action', 'sub_master_updated')->firstOrFail();
        $this->assertSame([['schools', 'delete'], ['backup']], [$log->metadata['access_before'], $log->metadata['access_after']]);
    }

    public function test_an_empty_checklist_can_be_saved_and_an_absent_one_leaves_it_alone(): void
    {
        $master = $this->master();
        $sub = User::factory()->subMaster(['schools'])->create();

        $this->actingAs($master)->put(route('master-user.sub-masters.update', $sub), $this->details($sub))->assertSessionHasNoErrors();
        $this->assertSame(['schools'], $sub->fresh()->access);

        $this->actingAs($master)->put(route('master-user.sub-masters.update', $sub), $this->details($sub, ['access_present' => 1]))->assertSessionHasNoErrors();
        $this->assertSame([], $sub->fresh()->access);
    }

    public function test_unknown_keys_or_a_non_array_checklist_are_refused_and_nothing_is_saved(): void
    {
        $master = $this->master();
        $sub = User::factory()->subMaster(['schools'])->create();

        $this->actingAs($master)->put(route('master-user.sub-masters.update', $sub), $this->details($sub, ['access_present' => 1, 'access' => ['schools', 'god_mode']]))->assertSessionHasErrors();
        $this->actingAs($master)->put(route('master-user.sub-masters.update', $sub), $this->details($sub, ['access_present' => 1, 'access' => 'schools']))->assertSessionHasErrors('access');
        $this->actingAs($master)->post(route('master-user.sub-masters.store'), $this->newAccount(['access_present' => 1, 'access' => ['nope']]))->assertSessionHasErrors();

        $this->assertSame(['schools'], $sub->fresh()->access);
        $this->assertSame(1, User::where('role', 'sub_master')->count());
    }

    public function test_email_and_username_must_be_unique_and_the_password_needs_12_characters(): void
    {
        $master = $this->master();
        User::factory()->create(['email' => 'taken@example.com', 'username' => 'taken.name']);

        $this->actingAs($master)->post(route('master-user.sub-masters.store'), $this->newAccount(['email' => 'taken@example.com', 'username' => 'taken.name']))->assertSessionHasErrors(['email', 'username']);
        $this->actingAs($master)->post(route('master-user.sub-masters.store'), $this->newAccount(['password' => 'short-pass1', 'password_confirmation' => 'short-pass1']))->assertSessionHasErrors('password');
        $this->actingAs($master)->post(route('master-user.sub-masters.store'), $this->newAccount(['password_confirmation' => 'different-Passphrase-2']))->assertSessionHasErrors('password');
        $this->assertSame(0, User::where('role', 'sub_master')->count());
    }

    public function test_role_status_school_and_organization_cannot_be_posted_into_a_new_account(): void
    {
        $this->actingAs($this->master())->post(route('master-user.sub-masters.store'), $this->newAccount(['role' => 'master_user', 'status' => 'inactive', 'school_id' => 1, 'organization_id' => 1]))->assertSessionHasNoErrors();

        $sub = User::where('email', 'helper@example.com')->firstOrFail();
        $this->assertSame(['sub_master', 'active', null, null], [$sub->role, $sub->status, $sub->school_id, $sub->organization_id]);
    }

    public function test_the_master_resets_a_sub_masters_password_and_the_old_one_stops_working(): void
    {
        $master = $this->master();
        $sub = User::factory()->subMaster()->create();

        $this->actingAs($master)->put(route('master-user.sub-masters.password', $sub), ['password' => 'another-Strong-Pass-2', 'password_confirmation' => 'another-Strong-Pass-2'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Hash::check('another-Strong-Pass-2', $sub->fresh()->password));
        $this->assertFalse(Hash::check('password', $sub->fresh()->password));
        $this->assertNotNull($sub->fresh()->password_changed_at);
        $this->assertSame(1, AuditLog::where('action', 'sub_master_password_reset')->count());
        $this->actingAs($master)->put(route('master-user.sub-masters.password', $sub), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    }

    public function test_the_master_deactivates_and_reactivates_a_sub_master(): void
    {
        $master = $this->master();
        $sub = User::factory()->subMaster()->create();

        $this->actingAs($master)->put(route('master-user.sub-masters.status', $sub), ['status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->deactivated_at);

        $this->actingAs($master)->put(route('master-user.sub-masters.status', $sub), ['status' => 'active'])->assertRedirect();
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertNull($sub->fresh()->deactivated_at);
        $this->assertSame(2, AuditLog::where('action', 'sub_master_status_changed')->count());
        $this->actingAs($master)->put(route('master-user.sub-masters.status', $sub), ['status' => 'deleted'])->assertSessionHasErrors('status');
    }

    public function test_a_sub_master_or_school_admin_gets_403_on_all_four_routes(): void
    {
        $target = User::factory()->subMaster()->create();
        $actors = [User::factory()->subMaster()->create(), User::factory()->create(['role' => 'school_admin'])];

        foreach ($actors as $actor) {
            $this->actingAs($actor)->post(route('master-user.sub-masters.store'), $this->newAccount())->assertForbidden();
            $this->actingAs($actor)->put(route('master-user.sub-masters.update', $target), $this->details($target))->assertForbidden();
            $this->actingAs($actor)->put(route('master-user.sub-masters.password', $target), ['password' => 'another-Strong-Pass-2', 'password_confirmation' => 'another-Strong-Pass-2'])->assertForbidden();
            $this->actingAs($actor)->put(route('master-user.sub-masters.status', $target), ['status' => 'inactive'])->assertForbidden();
        }
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_the_routes_only_work_on_sub_masters(): void
    {
        $master = $this->master();
        $other = User::factory()->create(['role' => 'school_admin']);
        $anotherMaster = User::factory()->create(['role' => 'master_user']);

        foreach ([$other, $anotherMaster] as $target) {
            $this->actingAs($master)->put(route('master-user.sub-masters.update', $target), $this->details($target))->assertNotFound();
            $this->actingAs($master)->put(route('master-user.sub-masters.status', $target), ['status' => 'inactive'])->assertNotFound();
        }
    }

    public function test_the_tab_lists_sub_masters_with_an_access_summary_and_the_add_form(): void
    {
        $master = $this->master();
        User::factory()->subMaster(['schools', 'backup', 'planning'])->create(['name' => 'Listed Helper']);

        $this->actingAs($master)->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('Sub-masters')->assertSee('Listed Helper')->assertSee('3 of 13')->assertSee('Add Sub-master')
            ->assertSee('name="access[]"', false)->assertSee('School Management');
    }
}
