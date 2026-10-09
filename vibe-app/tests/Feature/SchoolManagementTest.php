<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolManagementService;
use App\Services\StationTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role, 'password' => 'a-good-password']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay()]);

        return [$organization, $school, $user];
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function deactivation(array $extra = []): array
    {
        return ['reason' => 'Retired', 'effective_date' => now()->toDateString(), 'note' => 'Thank you for your service', ...$extra];
    }

    public function test_only_the_master_user_can_use_school_management(): void
    {
        [, $school, $user] = $this->tenant('perm');

        $this->actingAs($user)->get(route('school-management'))->assertForbidden();
        $this->get(route('school-management.show', $school))->assertForbidden();
        $this->post(route('school-management.status', $school), ['active' => 0])->assertForbidden();
        $this->post(route('school-management.users.deactivate', $user), $this->deactivation())->assertForbidden();

        $this->actingAs($this->master())->get(route('school-management'))->assertOk()->assertSee('School Management');
        $this->get(route('school-management.show', $school))->assertOk()->assertSee($school->name);
        $this->assertSame('active', $user->fresh()->status ?: 'active');
    }

    public function test_the_list_shows_active_vacant_and_inactive_schools_and_filters_them(): void
    {
        [, $active] = $this->tenant('alpha');
        [, $vacant, $leaver] = $this->tenant('bravo');
        [$orgC, $inactive] = $this->tenant('charlie');
        $leaver->update(['status' => 'inactive']);
        $inactive->update(['status' => 'inactive']);

        $this->actingAs($this->master())->get(route('school-management'))->assertOk()
            ->assertSee($active->name)->assertSee($vacant->name)->assertSee($inactive->name);
        $this->get(route('school-management', ['status' => 'vacant']))->assertSee($vacant->name)->assertDontSee($active->name)->assertDontSee($inactive->name);
        $this->get(route('school-management', ['status' => 'inactive']))->assertSee($inactive->name)->assertDontSee($active->name);
        $this->get(route('school-management', ['q' => 'ALPHA']))->assertSee($active->name)->assertDontSee($vacant->name);
    }

    public function test_setting_a_school_inactive_blocks_sign_in_and_transfers_into_it(): void
    {
        [$organization, $school, $user] = $this->tenant('dormant');
        [, , $other] = $this->tenant('mover');
        $master = $this->master();
        $user->update(['status' => 'inactive']);

        $this->actingAs($master)->post(route('school-management.status', $school), ['active' => 0])->assertRedirect();
        $this->assertSame('inactive', $school->fresh()->status);
        $this->assertSame('inactive', $organization->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'school_set_inactive']);

        try {
            app(StationTransferService::class)->request($other, ['to_school_id' => $school->id, 'reason' => 'x']);
            $this->fail('An inactive school cannot receive a user.');
        } catch (ValidationException) {
            $this->assertSame(0, StationTransferRequest::count());
        }

        $this->post(route('school-management.status', $school), ['active' => 1])->assertRedirect();
        $this->assertSame('active', $school->fresh()->status);
        $this->assertSame('active', $organization->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'school_reactivated']);
    }

    public function test_a_school_set_inactive_signs_its_user_out(): void
    {
        [, $school, $user] = $this->tenant('signout-school');
        $master = $this->master();

        $this->actingAs($user)->get(route('procurement'))->assertOk();
        School::whereKey($school->id)->update(['status' => 'inactive']);

        $this->get(route('procurement'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($master)->get(route('school-management'))->assertOk();
    }

    public function test_setting_a_user_inactive_makes_the_school_vacant_and_keeps_everything_else(): void
    {
        [, $school, $user] = $this->tenant('retire');
        $master = $this->master();
        $subscription = $user->activeSubscription();

        $this->actingAs($master)->post(route('school-management.users.deactivate', $user), $this->deactivation())->assertRedirect();

        $user->refresh();
        $this->assertSame('inactive', $user->status);
        $this->assertSame('Retired', $user->deactivation_reason);
        $this->assertNotNull($user->deactivated_at);
        $this->assertSame($school->id, $user->school_id);
        $this->assertSame($subscription->id, $user->activeSubscription()->id);
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'user_set_inactive', 'auditable_id' => $user->id]);
        $this->actingAs($master)->get(route('school-management'))->assertSee('Vacant');

        // The school can now receive a transferred user.
        [, , $mover] = $this->tenant('mover2');
        $request = app(StationTransferService::class)->request($mover, ['to_school_id' => $school->id, 'reason' => 'x']);
        $this->assertSame('pending', $request->status);

        // And the retired user can no longer sign in.
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-good-password']);
        $this->assertGuest();
    }

    public function test_a_signed_in_user_set_inactive_is_signed_out_on_the_next_request(): void
    {
        [, , $user] = $this->tenant('signout-user');
        $master = $this->master();

        $this->actingAs($user)->get(route('procurement'))->assertOk();
        app(SchoolManagementService::class)->deactivateUser($user, $master, 'Resigned', now()->toDateString(), null);

        $this->get(route('procurement'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivation_needs_a_reason_and_a_date_that_is_not_in_the_future(): void
    {
        [, , $user] = $this->tenant('rules');
        $this->actingAs($this->master());

        $this->post(route('school-management.users.deactivate', $user), $this->deactivation(['reason' => '']))->assertSessionHasErrors('reason');
        $this->post(route('school-management.users.deactivate', $user), $this->deactivation(['reason' => 'Vacation']))->assertSessionHasErrors('reason');
        $this->post(route('school-management.users.deactivate', $user), $this->deactivation(['effective_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('effective_date');
        $this->assertSame('active', $user->fresh()->status ?: 'active');

        $this->post(route('school-management.users.deactivate', $user), $this->deactivation())->assertSessionHasNoErrors();
        $this->post(route('school-management.users.deactivate', $user), $this->deactivation())->assertSessionHasErrors();
    }

    public function test_reactivating_a_user_follows_the_one_user_per_school_rule(): void
    {
        [$organization, $school, $user] = $this->tenant('back');
        $master = $this->master();
        $this->actingAs($master)->post(route('school-management.users.deactivate', $user), $this->deactivation());
        $replacement = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        $this->post(route('school-management.users.reactivate', $user))->assertSessionHasErrors();
        $this->assertSame('inactive', $user->fresh()->status);

        $replacement->update(['status' => 'inactive']);
        $school->update(['status' => 'inactive']);
        $this->post(route('school-management.users.reactivate', $user))->assertSessionHasErrors();

        $school->update(['status' => 'active']);
        $this->post(route('school-management.users.reactivate', $user))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->deactivated_at);
        $this->assertNull($user->deactivation_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_reactivated', 'auditable_id' => $user->id]);
    }

    public function test_the_master_user_cannot_be_set_inactive_here(): void
    {
        $master = $this->master();

        $this->actingAs($master)->post(route('school-management.users.deactivate', $master), $this->deactivation())->assertSessionHasErrors();
        $this->assertSame('active', $master->fresh()->status ?: 'active');
    }

    public function test_the_detail_page_shows_school_and_user_details_but_no_employees(): void
    {
        [$organization, $school, $user] = $this->tenant('detail');
        $school->update(['division' => 'Detail Division', 'district' => 'District Nine', 'address' => '1 Main Street']);
        SchoolStaff::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => 'Hidden Employee']);

        $this->actingAs($this->master())->get(route('school-management.show', $school))->assertOk()
            ->assertSee('School details')->assertSee('Detail Division')->assertSee('District Nine')->assertSee('1 Main Street')
            ->assertSee('User details')->assertSee($user->name)->assertSee($user->email)->assertSee('Professional')
            ->assertDontSee('Hidden Employee')->assertDontSee('Employees');
        $this->get(route('school-management'))->assertDontSee('Employees');
    }

    public function test_the_school_page_shows_the_pending_transfer_request_with_approve_and_decline(): void
    {
        [, $from, $user] = $this->tenant('leaving');
        $to = School::create(['organization_id' => Organization::create(['name' => 'dest', 'slug' => 'dest', 'status' => 'active'])->id, 'code' => 'DEST', 'name' => 'Destination School', 'status' => 'active']);
        $master = $this->master();
        app(StationTransferService::class)->request($user, ['to_school_id' => $to->id, 'reason' => 'Division order 12']);

        foreach ([$from, $to] as $school) {
            $this->actingAs($master)->get(route('school-management.show', $school))->assertOk()
                ->assertSee('Transfer request')->assertSee('Reason for transfer')->assertSee($user->name)->assertSee('Destination School')->assertSee('Division order 12')
                ->assertSee('Approve')->assertSee('Decline');
        }
        $this->get(route('school-management'))->assertSee(route('school-management.show', $from).'#transfer', false);

        $request = StationTransferRequest::first();
        $this->post(route('transfer-requests.approve', $request), ['back' => 'school'])->assertRedirect(route('school-management.show', $from));
        $this->assertSame($to->id, $user->fresh()->school_id);
    }
}
