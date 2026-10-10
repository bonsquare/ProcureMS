<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\SchoolTakeoverRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use App\Support\SubMasterAccess;
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
            'registration_type' => 'takeover',
            'system_user_given_name' => 'Nina', 'system_user_middle_initial' => 'R', 'system_user_surname' => 'Lopez', 'system_user_username' => 'nina.lopez',
            'system_user_position' => 'Administrative Officer', 'system_user_email' => 'nina@example.com', 'system_user_phone' => '09170000001',
            'system_user_password' => 'secret-pass-1', 'system_user_password_confirmation' => 'secret-pass-1', 'system_user_confirmed' => '1',
            ...$extra,
        ];
    }

    /** A takeover request as the public form makes it: the person picks no school. */
    private function takeover(array $extra = []): SchoolTakeoverRequest
    {
        $this->post(route('register.store'), $this->registration($extra))->assertRedirect();

        return SchoolTakeoverRequest::whereNull('school_id')->latest('id')->firstOrFail();
    }

    public function test_the_register_page_asks_for_no_school_in_the_takeover_option(): void
    {
        $this->school('vacant');

        $this->get(route('register'))->assertOk()->assertSee('Take over a vacant school')
            ->assertDontSee('vacant School')->assertDontSee('name="takeover_school_id"', false)->assertDontSee('Choose a school')
            ->assertSee('name="takeover_note"', false)->assertSee('Official Station');
    }

    public function test_the_register_page_headings_are_in_title_case(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('School Details')->assertSee('System User Information')
            ->assertDontSee('School details')->assertDontSee('System user information')->assertDontSee('>System user<', false);
    }

    public function test_registering_creates_a_pending_person_and_a_request_without_a_school(): void
    {
        $this->school('waiting');
        $schools = School::withoutGlobalScopes()->count();
        $organizations = Organization::count();

        $request = $this->takeover(['takeover_note' => 'Waiting School, Cebu City']);

        $user = User::where('username', 'nina.lopez')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->school_id);
        $this->assertNull($user->organization_id);
        $this->assertSame('school_admin', $user->role);
        $this->assertSame($user->id, $request->user_id);
        $this->assertNull($request->school_id);
        $this->assertSame('Waiting School, Cebu City', $request->note);
        $this->assertSame('pending', $request->status);
        $this->assertSame($schools, School::withoutGlobalScopes()->count());
        $this->assertSame($organizations, Organization::count());
        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }

    public function test_a_school_posted_by_the_form_is_ignored_and_the_note_is_optional(): void
    {
        $school = $this->school('posted');

        $request = $this->takeover(['takeover_school_id' => $school->id]);

        $this->assertNull($request->school_id);
        $this->assertNull($request->note);
    }

    public function test_the_note_is_limited_and_the_personal_information_is_still_required(): void
    {
        $this->post(route('register.store'), $this->registration(['takeover_note' => str_repeat('x', 501)]))->assertSessionHasErrors('takeover_note');
        $this->post(route('register.store'), $this->registration(['system_user_surname' => '']))->assertSessionHasErrors('system_user_surname');
        $this->assertSame(0, SchoolTakeoverRequest::count());
        $this->assertSame(0, User::where('status', 'pending')->count());
    }

    public function test_the_master_must_choose_a_vacant_school_when_approving(): void
    {
        $occupied = $this->school('busy', true);
        $closed = $this->school('shut', false, 'inactive');
        $claimed = $this->school('claimed');
        SchoolTakeoverRequest::create(['user_id' => User::factory()->create(['status' => 'pending', 'organization_id' => null, 'school_id' => null])->id, 'school_id' => $claimed->id, 'status' => 'pending']);
        $free = $this->school('free');
        $request = $this->takeover();
        $master = $this->master();

        foreach ([null, 999999, $occupied->id, $closed->id, $claimed->id] as $schoolId) {
            try {
                app(SchoolTakeoverService::class)->approve($request, $master, null, $schoolId);
                $this->fail('A school that is not open for a new user cannot be chosen.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('school_id', $exception->errors());
            }
        }
        $this->assertSame('pending', $request->fresh()->status);

        app(SchoolTakeoverService::class)->approve($request, $master, null, $free->id);
        $this->assertSame($free->id, User::where('username', 'nina.lopez')->value('school_id'));
    }

    public function test_a_pending_person_cannot_sign_in_and_is_told_why(): void
    {
        $this->takeover();

        $this->post(route('login.store'), ['email' => 'nina.lopez', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('waiting for the master account', session('errors')->first('email'));
    }

    public function test_the_master_approval_gives_the_person_the_chosen_school_and_its_data(): void
    {
        $school = $this->school('data');
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Existing Employee']);
        $request = $this->takeover();
        $master = $this->master();
        $this->actingAs($master);

        app(SchoolTakeoverService::class)->approve($request, $master, 'Welcome', $school->id);

        $user = User::where('username', 'nina.lopez')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertSame([$school->organization_id, $school->id], [$user->organization_id, $user->school_id]);
        $this->assertSame(['approved', $school->id, $master->id], [$request->fresh()->status, $request->fresh()->school_id, $request->fresh()->decided_by]);
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

    public function test_a_request_that_already_names_a_school_is_approved_without_choosing_again(): void
    {
        $school = $this->school('legacy');
        $user = User::factory()->create(['status' => 'pending', 'organization_id' => null, 'school_id' => null, 'role' => 'school_admin']);
        $request = SchoolTakeoverRequest::create(['user_id' => $user->id, 'school_id' => $school->id, 'status' => 'pending']);
        $master = $this->master();

        app(SchoolTakeoverService::class)->approve($request, $master);

        $this->assertSame($school->id, $user->fresh()->school_id);
    }

    public function test_approval_is_refused_when_the_chosen_school_got_a_user_meanwhile(): void
    {
        $school = $this->school('late');
        $request = $this->takeover();
        $master = $this->master();
        $this->actingAs($master);
        User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_admin']);

        try {
            app(SchoolTakeoverService::class)->approve($request, $master, null, $school->id);
            $this->fail('A school with a user cannot be taken over.');
        } catch (ValidationException) {
            $this->assertSame('pending', $request->fresh()->status);
            $this->assertNull(User::where('username', 'nina.lopez')->value('school_id'));
        }
    }

    public function test_a_declined_person_stays_out(): void
    {
        $request = $this->takeover();
        $master = $this->master();

        app(SchoolTakeoverService::class)->decline($request, $master, 'Not authorised');

        $this->assertSame('declined', $request->fresh()->status);
        $this->post(route('login.store'), ['email' => 'nina.lopez', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('declined', session('errors')->first('email'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['school_id' => null, 'action' => 'declined_school_takeover']);
    }

    public function test_only_the_master_decides_and_sees_the_request_in_official_station_management(): void
    {
        $school = $this->school('review');
        $request = $this->takeover();
        $other = $this->school('someone', true);
        $user = User::where('school_id', $other->id)->first();

        $this->actingAs($user)->post(route('school-takeover.approve', $request), ['school_id' => $school->id])->assertForbidden();
        $this->post(route('school-takeover.decline', $request))->assertForbidden();

        $master = $this->master();
        $html = $this->actingAs($master)->get(route('transfer-requests'))->assertOk()
            ->assertSee('Official Station Requests')->assertSee('Nina R. Lopez')->assertSee('nina@example.com')->assertSee('Official Station')
            ->assertSee('Approve')->assertSee('Decline')->getContent();
        preg_match('/<select name="school_id".*?<\/select>/s', $html, $options);
        $this->assertStringContainsString($school->name, $options[0]);
        $this->assertStringNotContainsString('someone School', $options[0]);

        $this->post(route('school-takeover.approve', $request), ['school_id' => $school->id, 'decision_note' => 'ok'])
            ->assertRedirect(route('transfer-requests'));
        $this->assertSame($school->id, User::where('username', 'nina.lopez')->value('school_id'));
    }

    public function test_approving_without_a_school_through_the_page_is_refused_and_stays_pending(): void
    {
        $this->school('free');
        $request = $this->takeover();

        $this->actingAs($this->master())->post(route('school-takeover.approve', $request), ['decision_note' => 'ok'])->assertSessionHasErrors('school_id');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_declining_through_the_page_returns_to_official_station_management(): void
    {
        $request = $this->takeover();

        $this->actingAs($this->master())->post(route('school-takeover.decline', $request), ['decision_note' => 'no'])->assertRedirect(route('transfer-requests'));

        $this->assertSame('declined', $request->fresh()->status);
    }

    public function test_a_sub_master_decides_only_with_the_transfers_access(): void
    {
        $school = $this->school('sub');
        $request = $this->takeover();
        $without = User::factory()->subMaster(array_values(array_diff(SubMasterAccess::defaults(), ['transfers'])))->create();
        $with = User::factory()->subMaster()->create();

        $this->actingAs($without)->post(route('school-takeover.approve', $request), ['school_id' => $school->id])->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($with)->post(route('school-takeover.approve', $request), ['school_id' => $school->id])->assertRedirect();
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_the_register_page_uses_a_checkbox_for_a_vacant_school(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('id="vacant-toggle"', false)->assertSee('type="checkbox"', false)
            ->assertSee('fill in only your own information')->assertSee('register a new school and fill in everything')
            ->assertSee('name="name"', false);
    }

    public function test_the_name_fields_line_up_on_one_row(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('grid grid-cols-1 items-end gap-4 sm:grid-cols-[1fr_9rem_1fr]', false)
            ->assertSee('whitespace-nowrap">Middle Initial', false);
    }
}
