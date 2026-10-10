<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'name' => 'Real Master']);
    }

    private function school(?string $name = null, bool $withUser = true, string $role = 'school_admin', array $userExtra = []): array
    {
        $n = ++$this->counter;
        $name ??= "School {$n}";
        $organization = Organization::create(['name' => $name, 'slug' => 'org-'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH'.$n, 'name' => $name, 'status' => 'active', 'division' => 'Div '.$n]);
        $user = $withUser ? User::factory()->create($userExtra + ['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id, 'status' => 'active']) : null;

        return [$school, $user];
    }

    private function sub(array $extra = [], array $without = []): User
    {
        return User::factory()->subMaster(array_values(array_diff([...SubMasterAccess::defaults(), ...$extra], $without)))->create();
    }

    // ---- the page: real numbers and a real list -------------------------------------------------

    public function test_the_metrics_and_the_list_are_real_and_leave_the_masters_out(): void
    {
        [, $a] = $this->school(userExtra: ['name' => 'Alice Active']);
        [, $b] = $this->school(userExtra: ['name' => 'Bob Inactive', 'status' => 'inactive']);
        User::factory()->create(['name' => 'Pat Pending', 'status' => 'pending', 'role' => 'school_admin', 'organization_id' => null, 'school_id' => null]);
        User::factory()->subMaster()->create(['name' => 'Hidden Sub']);
        User::factory()->create(['role' => 'master_user', 'name' => 'Hidden Master']);

        $html = $this->actingAs($this->master())->get(route('user-management'))->assertOk()
            ->assertSee('Alice Active')->assertSee('Bob Inactive')->assertSee('Pat Pending')->assertDontSee('Hidden Sub')->assertDontSee('Hidden Master')->assertDontSee('840')
            ->getContent();

        preg_match_all('/data-metric="([a-z_]+)">(\d+)</', $html, $m);
        $metrics = array_combine($m[1], array_map('intval', $m[2]));
        $this->assertSame(['total' => 3, 'active' => 1, 'pending' => 1, 'administrators' => 3], $metrics);
    }

    public function test_search_and_filters_narrow_the_list(): void
    {
        [$schoolA] = $this->school(userExtra: ['name' => 'Maria Santos', 'username' => 'maria.s', 'email' => 'maria@example.com']);
        [, $encoder] = $this->school(role: 'encoder', userExtra: ['name' => 'Juan Dela Cruz', 'username' => 'juan.dc', 'email' => 'juan@example.com']);
        [, $off] = $this->school(userExtra: ['name' => 'Rosa Reyes', 'status' => 'inactive']);
        $master = $this->master();

        $this->actingAs($master)->get(route('user-management', ['q' => 'maria.s']))->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
        $this->actingAs($master)->get(route('user-management', ['q' => 'juan@example']))->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');
        $this->actingAs($master)->get(route('user-management', ['role' => 'encoder']))->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos')->assertDontSee('Rosa Reyes');
        $this->actingAs($master)->get(route('user-management', ['status' => 'inactive']))->assertSee('Rosa Reyes')->assertDontSee('Maria Santos');
        $this->actingAs($master)->get(route('user-management', ['school_id' => $schoolA->id]))->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
    }

    public function test_the_list_is_paginated(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->school(userExtra: ['name' => sprintf('Person %02d', $i)]);
        }
        $master = $this->master();

        $this->actingAs($master)->get(route('user-management'))->assertSee('Person 01')->assertSee('Person 15')->assertDontSee('Person 16');
        $this->actingAs($master)->get(route('user-management', ['page' => 2]))->assertSee('Person 16')->assertSee('Person 17')->assertDontSee('Person 01');
    }

    public function test_users_by_role_and_access_activity_come_from_real_data(): void
    {
        $this->school();
        $this->school(role: 'encoder');
        $this->school(role: 'encoder');
        AuditLog::create(['user_id' => null, 'action' => 'approved_school_takeover', 'auditable_type' => User::class, 'auditable_id' => 1]);

        $this->actingAs($this->master())->get(route('user-management'))->assertOk()
            ->assertSee('Users by Role')->assertSee('Encoder')->assertSee('Approved school takeover')->assertSee(route('audit-logs.index'), false);
    }

    // ---- who may use it -----------------------------------------------------------------------

    public function test_only_the_master_or_a_sub_master_with_the_users_access_reaches_the_actions(): void
    {
        [, $user] = $this->school();
        $admin = $user;
        $without = $this->sub([], ['users']);
        $with = $this->sub();

        foreach ([$admin, $without] as $actor) {
            $this->actingAs($actor)->get(route('user-management.users.show', $user->id))->assertForbidden();
            $this->actingAs($actor)->put(route('user-management.users.update', $user->id), ['email' => 'x@example.com', 'role' => 'encoder'])->assertForbidden();
            $this->actingAs($actor)->put(route('user-management.users.password', $user->id), ['password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertForbidden();
            $this->actingAs($actor)->post(route('user-management.users.deactivate', $user->id), [])->assertForbidden();
            $this->actingAs($actor)->post(route('user-management.users.reactivate', $user->id))->assertForbidden();
            $this->actingAs($actor)->get(route('audit-logs.index'))->assertForbidden();
        }
        $this->actingAs($with)->get(route('user-management.users.show', $user->id))->assertOk();
        $this->actingAs($with)->get(route('audit-logs.index'))->assertOk();
    }

    public function test_masters_and_sub_masters_cannot_be_managed_here(): void
    {
        $master = $this->master();
        $other = User::factory()->create(['role' => 'master_user']);
        $sub = $this->sub();

        foreach ([$other, $sub] as $target) {
            $this->actingAs($master)->get(route('user-management.users.show', $target->id))->assertNotFound();
            $this->actingAs($master)->put(route('user-management.users.update', $target->id), ['email' => 'x@example.com', 'role' => 'encoder'])->assertNotFound();
            $this->actingAs($master)->put(route('user-management.users.password', $target->id), ['password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])->assertNotFound();
        }
        $this->assertTrue(Hash::check('password', $sub->fresh()->password));
    }

    // ---- view, edit, password ---------------------------------------------------------------------

    public function test_the_details_page_shows_the_user_school_and_subscription(): void
    {
        [$school, $user] = $this->school('Lubas Elementary School', userExtra: ['name' => 'Nina Lopez', 'username' => 'nina.l', 'email' => 'nina@example.com', 'phone' => '0917']);
        Subscription::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'trial', 'status' => 'trial', 'starts_at' => now(), 'subscription_end' => now()->addDays(30)]);

        $this->actingAs($this->master())->get(route('user-management.users.show', $user->id))->assertOk()
            ->assertSee('Nina Lopez')->assertSee('nina.l')->assertSee('nina@example.com')->assertSee('Lubas Elementary School')->assertSee($user->user_code)->assertSee('trial');
    }

    public function test_the_master_edits_email_phone_position_and_role_but_never_name_or_username(): void
    {
        [, $user] = $this->school(userExtra: ['name' => 'Keep Name', 'username' => 'keep.me']);
        $master = $this->master();

        $this->actingAs($master)->put(route('user-management.users.update', $user->id), [
            'email' => 'new@example.com', 'phone' => '09170000009', 'position' => 'Cashier', 'role' => 'encoder', 'name' => 'Hacked', 'username' => 'hacked',
        ])->assertRedirect(route('user-management.users.show', $user->id))->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertSame(['new@example.com', '09170000009', 'Cashier', 'encoder', 'Keep Name', 'keep.me'], [$fresh->email, $fresh->phone, $fresh->position, $fresh->role, $fresh->name, $fresh->username]);
        $this->assertSame(1, AuditLog::where(['user_id' => $master->id, 'action' => 'user_updated'])->count());
    }

    public function test_edit_refuses_a_taken_email_an_unknown_role_and_the_master_roles(): void
    {
        [, $user] = $this->school();
        [, $other] = $this->school(userExtra: ['email' => 'taken@example.com']);
        $master = $this->master();

        $this->actingAs($master)->put(route('user-management.users.update', $user->id), ['email' => 'taken@example.com', 'role' => 'encoder'])->assertSessionHasErrors('email');
        foreach (['god', 'master_user', 'sub_master'] as $role) {
            $this->actingAs($master)->put(route('user-management.users.update', $user->id), ['email' => 'ok@example.com', 'role' => $role])->assertSessionHasErrors('role');
        }
        $this->assertSame('school_admin', $user->fresh()->role);
    }

    public function test_the_master_resets_a_users_password(): void
    {
        [, $user] = $this->school();
        $master = $this->master();

        $this->actingAs($master)->put(route('user-management.users.password', $user->id), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($master)->put(route('user-management.users.password', $user->id), ['password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1'])
            ->assertRedirect(route('user-management.users.show', $user->id))->assertSessionHas('success');

        $this->assertTrue(Hash::check('a-Strong-Passphrase-1', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_changed_at);
        $this->assertSame(1, AuditLog::where('action', 'user_password_reset')->count());
    }

    // ---- deactivate and reactivate -----------------------------------------------------------------

    public function test_deactivate_and_reactivate_follow_the_school_management_rules(): void
    {
        [$school, $user] = $this->school();
        $master = $this->master();

        $this->actingAs($master)->post(route('user-management.users.deactivate', $user->id), ['reason' => 'Retired', 'effective_date' => now()->toDateString(), 'note' => 'Thanks'])
            ->assertRedirect(route('user-management.users.show', $user->id))->assertSessionHas('success');
        $this->assertSame(['inactive', 'Retired'], [$user->fresh()->status, $user->fresh()->deactivation_reason]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_set_inactive', 'school_id' => $school->id]);

        $this->actingAs($master)->post(route('user-management.users.deactivate', $user->id), ['reason' => 'Retired', 'effective_date' => now()->toDateString()])->assertSessionHasErrors('user');
        $this->actingAs($master)->post(route('user-management.users.deactivate', $user->id), ['reason' => 'Because', 'effective_date' => now()->toDateString()])->assertSessionHasErrors('reason');

        $this->actingAs($master)->post(route('user-management.users.reactivate', $user->id))->assertRedirect(route('user-management.users.show', $user->id));
        $this->assertSame('active', $user->fresh()->status);

        // one active user per school
        $user->forceFill(['status' => 'inactive'])->save();
        User::factory()->create(['role' => 'encoder', 'organization_id' => $school->organization_id, 'school_id' => $school->id, 'status' => 'active']);
        $this->actingAs($master)->post(route('user-management.users.reactivate', $user->id))->assertSessionHasErrors('user');
        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_a_sub_master_needs_the_deactivate_switch_but_not_the_schools_access(): void
    {
        [, $user] = $this->school();
        $payload = ['reason' => 'Retired', 'effective_date' => now()->toDateString()];

        $without = $this->sub([], ['schools']);
        $this->actingAs($without)->post(route('user-management.users.deactivate', $user->id), $payload)->assertForbidden();
        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame(1, AuditLog::where(['user_id' => $without->id, 'action' => 'sub_master_action_refused'])->count());

        $with = $this->sub(['deactivate_user'], ['schools']);
        $this->actingAs($with)->post(route('user-management.users.deactivate', $user->id), $payload)->assertSessionHasNoErrors();
        $this->assertSame('inactive', $user->fresh()->status);
    }

    // ---- add a user -------------------------------------------------------------------------------------

    private function newUser(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nina R. Lopez', 'username' => 'nina.lopez', 'email' => 'nina@example.com', 'phone' => '09170000001', 'position' => 'Administrative Officer',
            'role' => 'school_admin', 'password' => 'a-Strong-Passphrase-1', 'password_confirmation' => 'a-Strong-Passphrase-1',
        ];
    }

    public function test_the_master_adds_a_user_to_a_vacant_school(): void
    {
        [$school] = $this->school('Vacant School', withUser: false);
        $master = $this->master();

        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $school->id]))
            ->assertRedirect()->assertSessionHas('success');

        $user = User::where('username', 'nina.lopez')->firstOrFail();
        $this->assertSame(['school_admin', 'active', $school->id, $school->organization_id], [$user->role, $user->status, $user->school_id, $user->organization_id]);
        $this->assertTrue(Hash::check('a-Strong-Passphrase-1', $user->password));
        $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $school->id)->where('name', 'Nina R. Lopez')->count());
        $this->assertSame('trial', $user->activeSubscription()->plan);
        $this->assertSame(1, AuditLog::where(['user_id' => $master->id, 'action' => 'user_created', 'school_id' => $school->id])->count());
    }

    public function test_adding_is_refused_for_a_school_that_is_not_vacant_and_for_bad_input(): void
    {
        [$busy] = $this->school('Busy School');
        [$free] = $this->school('Free School', withUser: false);
        $master = $this->master();
        User::factory()->create(['email' => 'nina@example.com', 'username' => 'taken.name']);

        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $busy->id, 'email' => 'other@example.com']))->assertSessionHasErrors('school_id');
        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => 99999]))->assertSessionHasErrors('school_id');
        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $free->id]))->assertSessionHasErrors('email');
        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $free->id, 'email' => 'new@example.com', 'username' => 'taken.name']))->assertSessionHasErrors('username');
        $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $free->id, 'email' => 'new@example.com', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        foreach (['god', 'master_user', 'sub_master'] as $role) {
            $this->actingAs($master)->post(route('user-management.users.store'), $this->newUser(['school_id' => $free->id, 'email' => 'new@example.com', 'role' => $role]))->assertSessionHasErrors('role');
        }
        $this->assertSame(0, User::where('username', 'nina.lopez')->count());
    }

    public function test_the_page_offers_add_user_with_only_vacant_schools(): void
    {
        $this->school('Busy School');
        $this->school('Open School', withUser: false);

        $html = $this->actingAs($this->master())->get(route('user-management'))->assertOk()->assertSee('Add User')->assertDontSee('Invite User')->getContent();
        preg_match('/<select name="school_id" required.*?<\/select>/s', $html, $options);

        $this->assertStringContainsString('Open School', $options[0]);
        $this->assertStringNotContainsString('Busy School', $options[0]);
    }

    // ---- the audit log ----------------------------------------------------------------------------------

    public function test_the_audit_log_page_lists_and_filters_events(): void
    {
        [$school, $user] = $this->school('Lubas Elementary School');
        AuditLog::create(['user_id' => $user->id, 'school_id' => $school->id, 'action' => 'user_set_inactive', 'auditable_type' => User::class, 'auditable_id' => $user->id, 'metadata' => ['reason' => 'Retired']]);
        AuditLog::create(['user_id' => null, 'school_id' => null, 'action' => 'approved_school_takeover', 'auditable_type' => User::class, 'auditable_id' => 1]);
        $master = $this->master();

        $this->actingAs($master)->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Audit log')->assertSee('User set inactive')->assertSee('Approved school takeover')->assertSee('Lubas Elementary School')->assertSee('Retired');
        $this->actingAs($master)->get(route('audit-logs.index', ['q' => 'takeover']))->assertSee('Approved school takeover')->assertDontSee('User set inactive');
        $this->actingAs($master)->get(route('audit-logs.index', ['school_id' => $school->id]))->assertSee('User set inactive')->assertDontSee('Approved school takeover');
    }
}
