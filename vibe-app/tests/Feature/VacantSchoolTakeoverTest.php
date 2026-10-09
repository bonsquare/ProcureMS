<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\SchoolTakeoverRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacantSchoolTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug, bool $withUser = false, string $status = 'active'): School
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => $status, 'division' => 'Div '.$slug]);
        if ($withUser) {
            User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        }

        return $school;
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function registration(array $extra = []): array
    {
        return [
            'registration_type' => 'takeover', 'takeover_school_id' => null,
            'system_user_given_name' => 'Nina', 'system_user_middle_initial' => 'R', 'system_user_surname' => 'Lopez', 'system_user_username' => 'nina.lopez',
            'system_user_position' => 'Administrative Officer', 'system_user_email' => 'nina@example.com', 'system_user_phone' => '09170000001',
            'system_user_password' => 'secret-pass-1', 'system_user_password_confirmation' => 'secret-pass-1', 'system_user_confirmed' => '1',
            ...$extra,
        ];
    }

    private function takeover(School $school): SchoolTakeoverRequest
    {
        $this->post(route('register.store'), $this->registration(['takeover_school_id' => $school->id]))->assertRedirect();

        return SchoolTakeoverRequest::firstOrFail();
    }

    public function test_the_register_page_offers_only_vacant_schools(): void
    {
        $vacant = $this->school('vacant');
        $this->school('occupied', true);
        $this->school('closed', false, 'inactive');
        $claimed = $this->school('claimed');
        SchoolTakeoverRequest::create(['user_id' => User::factory()->create(['status' => 'pending', 'organization_id' => null, 'school_id' => null])->id, 'school_id' => $claimed->id, 'status' => 'pending']);

        $this->get(route('register'))->assertOk()->assertSee('Take over a vacant school')
            ->assertSee($vacant->name)->assertDontSee('occupied School')->assertDontSee('closed School')->assertDontSee('claimed School');
    }

    public function test_registering_for_a_vacant_school_creates_a_pending_person_and_changes_nothing_else(): void
    {
        $school = $this->school('waiting');
        $schools = School::withoutGlobalScopes()->count();
        $organizations = Organization::count();

        $request = $this->takeover($school);

        $user = User::where('username', 'nina.lopez')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->school_id);
        $this->assertNull($user->organization_id);
        $this->assertSame('school_admin', $user->role);
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame($school->id, $request->school_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame($schools, School::withoutGlobalScopes()->count());
        $this->assertSame($organizations, Organization::count());
        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }

    public function test_a_school_that_is_not_vacant_cannot_be_chosen(): void
    {
        $occupied = $this->school('busy', true);
        $closed = $this->school('shut', false, 'inactive');
        $free = $this->school('free');
        $this->takeover($free);

        foreach ([$occupied, $closed, $free] as $school) {
            $this->post(route('register.store'), $this->registration(['takeover_school_id' => $school->id, 'system_user_username' => 'other.person', 'system_user_email' => 'other@example.com']))->assertSessionHasErrors('takeover_school_id');
        }
        $this->post(route('register.store'), $this->registration(['takeover_school_id' => null, 'system_user_username' => 'x.person', 'system_user_email' => 'x@example.com']))->assertSessionHasErrors('takeover_school_id');
        $this->assertSame(1, User::where('status', 'pending')->count());
        $this->assertSame(1, SchoolTakeoverRequest::count());
    }

    public function test_a_pending_person_cannot_sign_in_and_is_told_why(): void
    {
        $this->takeover($this->school('later'));

        $this->post(route('login.store'), ['email' => 'nina.lopez', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('waiting for the master account', session('errors')->first('email'));
    }

    public function test_the_master_approval_gives_the_person_the_school_and_its_data(): void
    {
        $school = $this->school('data');
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Existing Employee']);
        $request = $this->takeover($school);
        $master = $this->master();
        $this->actingAs($master);

        app(SchoolTakeoverService::class)->approve($request, $master, 'Welcome');

        $user = User::where('username', 'nina.lopez')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertSame([$school->organization_id, $school->id], [$user->organization_id, $user->school_id]);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($master->id, $request->fresh()->decided_by);
        $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $school->id)->where('name', $user->name)->count());
        $plan = $user->activeSubscription();
        $this->assertSame('trial', $plan->plan);
        $this->assertSame($user->id, $plan->user_id);
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'approved_school_takeover']);

        $this->actingAs($user);
        $this->assertContains('Existing Employee', SchoolStaff::pluck('name')->all());
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'nina.lopez', 'password' => 'secret-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_approval_is_refused_when_the_school_got_a_user_meanwhile(): void
    {
        $school = $this->school('late');
        $request = $this->takeover($school);
        $master = $this->master();
        $this->actingAs($master);
        User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_admin']);

        try {
            app(SchoolTakeoverService::class)->approve($request, $master);
            $this->fail('A school with a user cannot be taken over.');
        } catch (ValidationException) {
            $this->assertSame('pending', $request->fresh()->status);
            $this->assertNull(User::where('username', 'nina.lopez')->value('school_id'));
        }
    }

    public function test_a_declined_person_stays_out(): void
    {
        $request = $this->takeover($this->school('nope'));
        $master = $this->master();

        app(SchoolTakeoverService::class)->decline($request, $master, 'Not authorised');

        $this->assertSame('declined', $request->fresh()->status);
        $this->post(route('login.store'), ['email' => 'nina.lopez', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('declined', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_only_the_master_decides_and_sees_the_request_on_the_school_page(): void
    {
        $school = $this->school('review');
        $request = $this->takeover($school);
        $outsider = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_admin', 'status' => 'inactive']);
        $other = $this->school('someone', true);
        $user = User::where('school_id', $other->id)->first();

        $this->actingAs($user)->post(route('school-takeover.approve', $request))->assertForbidden();
        $this->post(route('school-takeover.decline', $request))->assertForbidden();

        $master = $this->master();
        $this->actingAs($master)->get(route('school-management.show', $school))->assertOk()
            ->assertSee('Takeover request')->assertSee('Nina R. Lopez')->assertSee('nina@example.com')->assertSee('Approve')->assertSee('Decline');
        $this->get(route('school-management'))->assertSee(route('school-management.show', $school).'#takeover', false);

        $this->post(route('school-takeover.approve', $request), ['decision_note' => 'ok'])->assertRedirect(route('school-management.show', $school));
        $this->assertSame($school->id, User::where('username', 'nina.lopez')->value('school_id'));
        $this->assertNotNull($outsider->fresh());
    }

    public function test_the_register_page_uses_a_checkbox_for_a_vacant_school(): void
    {
        $this->school('boxed');

        $this->get(route('register'))->assertOk()->assertSee('id="vacant-toggle"', false)->assertSee('type="checkbox"', false)
            ->assertSee('fill in only your own information')->assertSee('register a new school and fill in everything')
            ->assertSee('name="takeover_school_id"', false)->assertSee('name="name"', false);
    }
}
