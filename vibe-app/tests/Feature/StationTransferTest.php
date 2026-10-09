<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StationTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StationTransferTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);

        return [$organization, $school, $user];
    }

    private function vacantSchool(string $slug): School
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
    }

    private function transferTab(): string
    {
        return route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function staff(Organization $organization, School $school, string $name, array $extra = []): SchoolStaff
    {
        return SchoolStaff::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => $name, ...$extra]);
    }

    public function test_ended_staff_records_are_hidden_from_normal_queries_but_kept(): void
    {
        [$organization, $school] = $this->tenant('hide-a');
        $this->staff($organization, $school, 'Active Person');
        $ended = $this->staff($organization, $school, 'Former Person');
        $ended->update(['ended_at' => now()]);

        $this->assertSame(['Active Person'], SchoolStaff::pluck('name')->all());
        $this->assertSame(2, SchoolStaff::withoutGlobalScopes()->where('school_id', $school->id)->count());
    }

    public function test_a_users_own_subscription_wins_over_the_schools(): void
    {
        [$organization, $school, $user] = $this->tenant('sub-a');
        $own = Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'enterprise', 'billing_cycle' => 'annual', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDays(40)]);

        $this->assertSame($own->id, $user->activeSubscription()->id);
        $this->assertSame('professional', User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'viewer'])->activeSubscription()->plan, 'a legacy colleague without a plan keeps the school row');
    }

    public function test_registration_records_the_subscription_owner(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Delta Elementary School', 'system_user_given_name' => 'Dina', 'system_user_surname' => 'Cruz', 'system_user_username' => 'dina.cruz',
            'system_user_position' => 'Principal', 'system_user_email' => 'dina@example.com', 'system_user_phone' => '09170000000',
            'system_user_password' => 'secret-pass-1', 'system_user_password_confirmation' => 'secret-pass-1', 'system_user_confirmed' => '1',
        ])->assertRedirect();

        $this->assertSame(User::where('username', 'dina.cruz')->value('id'), Subscription::withoutGlobalScopes()->latest('id')->value('user_id'));
    }

    public function test_a_user_can_request_and_a_second_pending_request_is_refused(): void
    {
        [, , $user] = $this->tenant('req-a');
        $target = $this->vacantSchool('req-b');
        $service = app(StationTransferService::class);

        $request = $service->request($user, ['to_school_id' => $target->id, 'reason' => 'Reassigned']);

        $this->assertSame('pending', $request->status);
        $this->assertSame($user->school_id, $request->from_school_id);
        $this->expectException(ValidationException::class);
        $service->request($user, ['to_school_id' => $target->id, 'reason' => 'Again']);
    }

    public function test_request_rules_master_same_school_inactive_and_occupied_destination(): void
    {
        [, $school, $user] = $this->tenant('rule-a');
        $closed = $this->vacantSchool('rule-b');
        $closed->update(['status' => 'inactive']);
        [, $occupied] = $this->tenant('rule-c');
        $service = app(StationTransferService::class);

        foreach ([
            [$this->master(), ['to_school_id' => $school->id, 'reason' => 'x']],
            [$user, ['to_school_id' => $school->id, 'reason' => 'x']],
            [$user, ['to_school_id' => $closed->id, 'reason' => 'x']],
            [$user, ['to_school_id' => $occupied->id, 'reason' => 'x']],
            [$user, ['reason' => 'x']],
        ] as [$who, $data]) {
            try {
                $service->request($who, $data);
                $this->fail('Expected a validation error.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, StationTransferRequest::count());
    }

    public function test_approving_to_a_registered_school_moves_the_account_and_keeps_the_old_school_untouched(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('alpha');
        $schoolB = $this->vacantSchool('bravo');
        $orgB = $schoolB->organization;
        $this->staff($orgA, $schoolA, $user->name, ['position' => 'AO II', 'bac_role' => 'BAC Member']);
        $this->staff($orgA, $schoolA, 'Old Clerk');
        $this->staff($orgB, $schoolB, 'New Clerk');
        $subscription = Subscription::create(['organization_id' => $orgA->id, 'school_id' => $schoolA->id, 'user_id' => $user->id, 'plan' => 'enterprise', 'billing_cycle' => 'annual', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()]);
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $request = $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'Division order']), $master, 'Welcome');

        $user->refresh();
        $this->assertSame([$orgB->id, $schoolB->id], [$user->organization_id, $user->school_id]);
        $this->assertSame('school_admin', $user->role);
        $this->assertSame($subscription->id, $user->activeSubscription()->id);
        $this->assertFalse(Subscription::withoutGlobalScopes()->where('school_id', $schoolB->id)->exists(), 'a transfer creates no subscription');
        $this->assertSame('approved', $request->status);
        $this->assertSame($master->id, $request->decided_by);
        $this->assertNull($request->confirmed_at);

        $old = SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolA->id)->where('name', $user->name)->first();
        $this->assertNotNull($old->ended_at);
        $this->assertSame('BAC Member', $old->bac_role);
        $new = SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->first();
        $this->assertNotNull($new);
        $this->assertNull($new->bac_role);
        $this->assertNotSame($old->employee_no, $new->employee_no);

        $this->actingAs($user);
        $names = SchoolStaff::pluck('name')->all();
        $this->assertContains('New Clerk', $names);
        $this->assertNotContains('Old Clerk', $names);
        $this->assertSame(2, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolA->id)->count());
    }

    public function test_approving_a_school_that_is_not_registered_creates_it(): void
    {
        [, , $user] = $this->tenant('src');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $request = $service->approve($service->request($user, ['proposed_school' => ['name' => 'Charlie Elementary School', 'division' => 'Div X'], 'reason' => 'New post']), $master);

        $school = School::withoutGlobalScopes()->where('name', 'Charlie Elementary School')->firstOrFail();
        $this->assertSame('active', $school->status);
        $this->assertStringStartsWith('SCH-', $school->code);
        $this->assertSame('Div X', $school->division);
        $this->assertSame($school->id, $request->fresh()->to_school_id);
        $this->assertSame($school->id, $user->fresh()->school_id);
        $this->assertSame($school->organization_id, $user->fresh()->organization_id);
        $this->assertNull(Subscription::withoutGlobalScopes()->where('school_id', $school->id)->first(), 'the plan comes from the user, not the school');
    }

    public function test_approving_twice_or_for_an_inactive_user_changes_nothing(): void
    {
        [, , $user] = $this->tenant('twice-a');
        $schoolB = $this->vacantSchool('twice-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $request = $service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']);
        $service->approve($request, $master);

        try {
            $service->approve($request, $master);
            $this->fail('Second approval must fail.');
        } catch (ValidationException) {
            $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->count());
        }

        [, $schoolC, $leaver] = $this->tenant('twice-c');
        $pending = $service->request($leaver, ['proposed_school' => ['name' => 'Never Created School'], 'reason' => 'x']);
        $leaver->update(['status' => 'inactive']);
        try {
            $service->approve($pending, $master);
            $this->fail('Inactive user must block approval.');
        } catch (ValidationException) {
            $this->assertSame($schoolC->id, $leaver->fresh()->school_id);
            $this->assertNull(School::withoutGlobalScopes()->where('name', 'Never Created School')->first());
        }
    }

    public function test_a_failure_after_the_school_is_created_rolls_everything_back(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('boom-a');
        $this->staff($orgA, $schoolA, $user->name);
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $request = $service->request($user, ['proposed_school' => ['name' => 'Rolled Back School'], 'reason' => 'x']);

        SchoolStaff::creating(fn () => throw new \RuntimeException('boom'));
        try {
            $service->approve($request, $master);
            $this->fail('Expected the forced failure.');
        } catch (\RuntimeException) {
            $this->assertNull(School::withoutGlobalScopes()->where('name', 'Rolled Back School')->first());
            $this->assertSame($schoolA->id, $user->fresh()->school_id);
            $this->assertNull(SchoolStaff::withoutGlobalScopes()->where('name', $user->name)->first()->ended_at);
            $this->assertSame('pending', $request->fresh()->status);
        } finally {
            SchoolStaff::clearBootedModels();
        }
    }

    public function test_a_user_without_an_employee_record_can_still_be_moved(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('noemp-a');
        $schoolB = $this->vacantSchool('noemp-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']), $master);

        $this->assertSame($schoolB->id, $user->fresh()->school_id);
        $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->count());

        // The school they left has no employees and no user, but it stays active; it is only vacant.
        $this->assertSame('active', $schoolA->fresh()->status);
        $this->assertSame('active', $orgA->fresh()->status);
        $this->actingAs($master)->get(route('school-management', ['status' => 'vacant']))->assertSee($schoolA->name);
        $this->get(route('school-management', ['status' => 'inactive']))->assertDontSee($schoolA->name);
    }

    public function test_decline_and_cancel(): void
    {
        [, $schoolA, $admin] = $this->tenant('dec-a');
        [, , $other] = $this->tenant('dec-v', 'viewer');
        $schoolB = $this->vacantSchool('dec-b');
        $master = $this->master();
        $service = app(StationTransferService::class);

        $declined = $service->request($admin, ['to_school_id' => $schoolB->id, 'reason' => 'x']);
        $service->decline($declined, $master, 'Not now');
        $this->assertSame('declined', $declined->fresh()->status);
        $this->assertSame($schoolA->id, $admin->fresh()->school_id);

        $again = $service->request($admin, ['to_school_id' => $schoolB->id, 'reason' => 'y']);
        try {
            $service->cancel($again, $other);
            $this->fail('Only the owner may cancel.');
        } catch (ValidationException) {
            $service->cancel($again, $admin);
            $this->assertSame('cancelled', $again->fresh()->status);
        }
    }

    public function test_a_destination_that_gets_a_user_before_approval_is_refused(): void
    {
        [, $schoolA, $user] = $this->tenant('taken-a');
        $schoolB = $this->vacantSchool('taken-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $request = $service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']);
        User::factory()->create(['organization_id' => $schoolB->organization_id, 'school_id' => $schoolB->id, 'role' => 'school_admin']);

        try {
            $service->approve($request, $master);
            $this->fail('A school with a user cannot receive another one.');
        } catch (ValidationException) {
            $this->assertSame($schoolA->id, $user->fresh()->school_id);
            $this->assertSame('pending', $request->fresh()->status);
        }
    }

    public function test_confirm_sets_the_timestamp_once(): void
    {
        [, , $user] = $this->tenant('conf-a');
        $schoolB = $this->vacantSchool('conf-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']), $master);

        $this->assertTrue($service->confirm($user->fresh()));
        $this->assertFalse($service->confirm($user->fresh()));
        $this->assertNotNull(StationTransferRequest::first()->confirmed_at);
    }

    public function test_a_user_submits_a_request_over_http(): void
    {
        [, , $user] = $this->tenant('http-a');
        $target = $this->vacantSchool('http-b');

        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'registered', 'to_school_id' => $target->id, 'reason' => 'Reassigned'])
            ->assertRedirect($this->transferTab());
        $this->assertSame('pending', StationTransferRequest::first()->status);

        $this->post(route('station-transfer.store'), ['destination' => 'registered', 'to_school_id' => $target->id, 'reason' => 'Again'])->assertSessionHasErrors('reason');
        $this->post(route('station-transfer.store'), ['destination' => 'new', 'new_school' => ['name' => ''], 'reason' => 'x'])->assertSessionHasErrors('new_school.name');
        $this->post(route('station-transfer.cancel', StationTransferRequest::first()))->assertRedirect($this->transferTab());
        $this->assertSame('cancelled', StationTransferRequest::first()->status);
    }

    public function test_only_the_master_can_see_and_decide_requests(): void
    {
        [, , $user] = $this->tenant('perm-a');
        $target = $this->vacantSchool('perm-b');
        $request = app(StationTransferService::class)->request($user, ['to_school_id' => $target->id, 'reason' => 'x']);

        $this->actingAs($user)->get(route('transfer-requests'))->assertForbidden();
        $this->post(route('transfer-requests.approve', $request))->assertForbidden();
        $this->post(route('transfer-requests.decline', $request))->assertForbidden();

        $this->actingAs($this->master())->post(route('transfer-requests.approve', $request), ['decision_note' => 'ok'])->assertRedirect(route('transfer-requests'));
        $this->assertSame($target->id, $user->fresh()->school_id);
    }

    public function test_an_unconfirmed_user_is_sent_to_the_confirmation_page_but_can_log_out(): void
    {
        [, , $user] = $this->tenant('mid-a');
        $target = $this->vacantSchool('mid-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $service->approve($service->request($user, ['to_school_id' => $target->id, 'reason' => 'x']), $master);

        $this->actingAs($user->fresh());
        $this->get(route('home'))->assertRedirect(route('station.confirm'));
        $this->get(route('procurement'))->assertRedirect(route('station.confirm'));
        $this->get(route('station.confirm'))->assertOk()->assertSee($target->name);
        $this->post(route('logout'))->assertRedirect();

        $this->actingAs($user->fresh())->post(route('station.confirm.store'))->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();
    }

    public function test_the_screens_show_the_right_content(): void
    {
        [, $schoolA, $user] = $this->tenant('ui-a');
        [, $busy] = $this->tenant('ui-busy');
        $schoolB = $this->vacantSchool('ui-b');
        $service = app(StationTransferService::class);
        $master = $this->master();

        $this->actingAs($user)->get($this->transferTab())->assertOk()
            ->assertSee('Request a station transfer')->assertSee($schoolB->name)->assertSee('My school isn')
            ->assertDontSee($busy->name);

        $request = $service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'Division order']);
        $this->get($this->transferTab())->assertSee('Pending')->assertSee('Division order')->assertSee('Cancel request');

        $this->actingAs($master)->get(route('transfer-requests'))->assertOk()
            ->assertSee($user->name)->assertSee($schoolB->name)->assertSee('will have no user')->assertSee('Approve')->assertSee('Decline');

        $service->approve($request, $master, 'Welcome aboard');
        $this->actingAs($master)->get(route('transfer-requests'))->assertSee('Approved')->assertSee('Welcome aboard');
        $this->actingAs($user->fresh())->get(route('station.confirm'))->assertOk()->assertSee('Confirm your new station')->assertSee($schoolA->name)->assertSee($schoolB->name)->assertSee('School Admin');
    }

    public function test_the_user_menu_links_to_the_right_page(): void
    {
        [, , $user] = $this->tenant('menu-a');
        $this->actingAs($user)->get(route('procurement'))->assertDontSee('tab=transfer', false)->assertDontSee(route('transfer-requests'), false);
        $this->actingAs($this->master())->get(route('procurement'))->assertSee(route('transfer-requests'), false)->assertDontSee('tab=transfer', false);
    }

    public function test_the_transfer_pages_are_not_shown_inside_procurement(): void
    {
        [, , $user] = $this->tenant('nav-a');
        $master = $this->master();

        foreach ([[$master, 'transfer-requests']] as [$who, $route]) {
            $html = $this->actingAs($who)->get(route($route))->assertOk()->getContent();
            $this->assertStringNotContainsString('civic-module-tabs', $html, $route.' must not show the Procurement tabs');
            $this->assertStringNotContainsString('Procurement Workspace', $html, $route.' must not be labelled Procurement');
            $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<span[^>]*>settings<\/span>/', $html, $route.' highlights School Settings, not Procurement');
        }
    }
}
