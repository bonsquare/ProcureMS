<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StationTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferHandoverTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin', 'password' => 'a-good-password']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay()]);

        return [$organization, $school, $user];
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function service(): StationTransferService
    {
        return app(StationTransferService::class);
    }

    /** An arriving user who asks for a school that already has a user. */
    private function occupiedRequest(string $slug = 'h'): array
    {
        [, $fromSchool, $arriving] = $this->tenant($slug.'-from');
        [, $toSchool, $occupant] = $this->tenant($slug.'-to');
        $request = $this->service()->request($arriving, ['to_school_id' => $toSchool->id, 'reason' => 'Division order']);

        return [$request, $arriving, $occupant, $fromSchool, $toSchool];
    }

    public function test_a_request_to_an_occupied_school_waits_for_that_schools_user(): void
    {
        [$request, , $occupant, , $toSchool] = $this->occupiedRequest();

        $this->assertSame('pending', $request->review_status);
        $this->assertSame($occupant->id, $request->reviewer_user_id);
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, $request->review_expires_at->timestamp, 5);

        // A second incoming transfer into the same school is refused while this one is open.
        [, , $other] = $this->tenant('h-other');
        try {
            $this->service()->request($other, ['to_school_id' => $toSchool->id, 'reason' => 'x']);
            $this->fail('Only one incoming transfer at a time.');
        } catch (ValidationException) {
            $this->assertSame(1, StationTransferRequest::count());
        }

        // A vacant destination needs no review.
        $vacant = School::create(['organization_id' => Organization::create(['name' => 'v', 'slug' => 'v', 'status' => 'active'])->id, 'code' => 'V', 'name' => 'Vacant School', 'status' => 'active']);
        $direct = $this->service()->request($other, ['to_school_id' => $vacant->id, 'reason' => 'x']);
        $this->assertSame('not_required', $direct->review_status);
        $this->assertNull($direct->review_expires_at);
    }

    public function test_only_the_destination_user_can_accept_or_decline_and_accepting_starts_the_handover_clock(): void
    {
        [$request, $arriving, $occupant] = $this->occupiedRequest();

        foreach ([$arriving, $this->master()] as $outsider) {
            try {
                $this->service()->review($request, $outsider, true, null);
                $this->fail('Only the destination user may review.');
            } catch (ValidationException) {
                $this->assertSame('pending', $request->fresh()->review_status);
            }
        }

        $this->service()->review($request, $occupant, true, 'Welcome');
        $request->refresh();
        $this->assertSame('accepted', $request->review_status);
        $this->assertSame('Welcome', $request->review_note);
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, $request->handover_ends_at->timestamp, 5);
        $this->assertSame($occupant->id, $request->handover_user_id);
        $this->assertSame('pending', $request->status);

        [$second, , $occupant2] = $this->occupiedRequest('d');
        $this->service()->review($second, $occupant2, false, 'No room');
        $this->assertSame('declined', $second->fresh()->status);
        $this->assertSame('declined', $second->fresh()->review_status);
    }

    public function test_the_master_cannot_approve_until_the_destination_accepted_and_both_users_keep_access_afterwards(): void
    {
        [$request, $arriving, $occupant, $fromSchool, $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);

        try {
            $this->service()->approve($request, $master);
            $this->fail('Approval needs the acceptance first.');
        } catch (ValidationException) {
            $this->assertSame('pending', $request->fresh()->status);
        }

        $this->service()->review($request, $occupant, true, null);
        $this->service()->approve($request, $master);

        $this->assertSame($toSchool->id, $arriving->fresh()->school_id);
        $this->assertSame($toSchool->id, $occupant->fresh()->school_id);
        $this->assertSame('active', $occupant->fresh()->status ?: 'active');
        $this->assertSame('active', $arriving->fresh()->status ?: 'active');
        $this->assertNull($request->fresh()->handover_ended_at);
        $this->assertSame('active', $fromSchool->fresh()->status);
    }

    public function test_the_previous_user_is_set_inactive_when_the_handover_ends(): void
    {
        [$request, $arriving, $occupant, , $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->service()->review($request, $occupant, true, null);
        $this->service()->approve($request, $master);

        $this->travel(4)->days();
        $this->artisan('transfers:maintain')->assertSuccessful();
        $this->assertSame('active', $occupant->fresh()->status ?: 'active');

        $this->travel(2)->days();
        $this->artisan('transfers:maintain')->assertSuccessful();
        $occupant->refresh();
        $this->assertSame('inactive', $occupant->status);
        $this->assertSame('Transferred', $occupant->deactivation_reason);
        $this->assertNotNull($request->fresh()->handover_ended_at);
        $this->assertSame('active', $arriving->fresh()->status ?: 'active');
        $this->assertDatabaseHas('audit_logs', ['school_id' => $toSchool->id, 'action' => 'handover_ended']);
    }

    public function test_a_previous_user_is_signed_out_at_the_deadline_even_before_the_daily_job_runs(): void
    {
        [$request, , $occupant] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->service()->review($request, $occupant, true, null);
        $this->service()->approve($request, $master);

        $this->actingAs($occupant)->get(route('procurement'))->assertOk();
        $this->travel(6)->days();

        $this->get(route('procurement'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('inactive', $occupant->fresh()->status);
    }

    public function test_approving_after_the_handover_window_ended_sets_the_previous_user_inactive_at_once(): void
    {
        [$request, $arriving, $occupant] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->travel(4)->days();
        $this->service()->review($request, $occupant, true, null);
        $this->travel(6)->days();

        $this->service()->approve($request, $master);

        $this->assertSame('inactive', $occupant->fresh()->status);
        $this->assertNotNull($request->fresh()->handover_ended_at);
        $this->assertSame($request->to_school_id, $arriving->fresh()->school_id);
    }

    public function test_a_request_nobody_answers_expires_after_five_days_and_can_be_sent_again(): void
    {
        [$request, $arriving, $occupant, , $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->travel(5)->days();
        $this->travel(1)->hours();

        $this->artisan('transfers:maintain')->assertSuccessful();
        $request->refresh();
        $this->assertSame('expired', $request->status);
        $this->assertNotNull($request->expired_at);

        foreach ([fn () => $this->service()->review($request, $occupant, true, null), fn () => $this->service()->approve($request, $master)] as $action) {
            try {
                $action();
                $this->fail('An expired request cannot be used.');
            } catch (ValidationException) {
                $this->assertSame('expired', $request->fresh()->status);
            }
        }

        $again = $this->service()->request($arriving, ['to_school_id' => $toSchool->id, 'reason' => 'Second try']);
        $this->assertSame('pending', $again->status);
        $this->assertSame('pending', $again->review_status);
    }

    public function test_an_unanswered_request_is_expired_when_the_user_sends_a_new_one_without_the_daily_job(): void
    {
        [, $arriving, , , $toSchool] = $this->occupiedRequest();
        $this->travel(6)->days();

        $again = $this->service()->request($arriving, ['to_school_id' => $toSchool->id, 'reason' => 'Again']);

        $this->assertSame('pending', $again->status);
        $this->assertSame(1, StationTransferRequest::where('status', 'expired')->count());
    }

    public function test_a_destination_user_who_left_before_approval_counts_as_a_vacant_school(): void
    {
        [$request, $arriving, $occupant, , $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->service()->review($request, $occupant, true, null);
        $occupant->update(['status' => 'inactive']);

        $this->service()->approve($request, $master);

        $this->assertSame($toSchool->id, $arriving->fresh()->school_id);
    }

    public function test_a_new_user_at_the_destination_blocks_approval(): void
    {
        [$request, $arriving, $occupant, , $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->service()->review($request, $occupant, true, null);
        User::factory()->create(['organization_id' => $toSchool->organization_id, 'school_id' => $toSchool->id, 'role' => 'school_admin']);

        try {
            $this->service()->approve($request, $master);
            $this->fail('A third active user cannot be added.');
        } catch (ValidationException) {
            $this->assertNotSame($toSchool->id, $arriving->fresh()->school_id);
        }
    }

    public function test_the_master_can_end_a_handover_early_and_only_the_master(): void
    {
        [$request, $arriving, $occupant, , $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $this->actingAs($master);
        $this->service()->review($request, $occupant, true, null);
        $this->service()->approve($request, $master);

        $this->actingAs($occupant)->post(route('school-management.handover.end', $request))->assertForbidden();
        $this->actingAs($master)->post(route('school-management.handover.end', $request))->assertRedirect(route('school-management.show', $toSchool));

        $this->assertSame('inactive', $occupant->fresh()->status);
        $this->assertNotNull($request->fresh()->handover_ended_at);
    }

    public function test_the_screens_show_the_review_and_the_handover(): void
    {
        [$request, $arriving, $occupant, $fromSchool, $toSchool] = $this->occupiedRequest();
        $master = $this->master();
        $tab = route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']);

        // The requester sees the destination can choose, and the occupied school is offered with a note.
        $this->actingAs($arriving)->get($tab)->assertOk()->assertSee('Waiting for '.$toSchool->name.' to accept');
        // The destination user sees the incoming request and can accept it from the page.
        $this->actingAs($occupant)->get($tab)->assertOk()->assertSee('Incoming transfer request')->assertSee($arriving->name)->assertSee('Division order')->assertSee('Accept')->assertSee('Decline');
        $this->post(route('station-transfer.review', $request), ['decision' => 'accept', 'note' => 'Welcome'])->assertRedirect($tab);
        $this->assertSame('accepted', $request->fresh()->review_status);

        // The master sees the review result on the school page and the queue.
        $this->actingAs($master)->get(route('school-management.show', $toSchool))->assertOk()->assertSee('Accepted by '.$toSchool->name);
        $this->get(route('transfer-requests'))->assertOk()->assertSee('Accepted');

        // After approval both users see the handover notice and the master can end it.
        $this->post(route('transfer-requests.approve', $request))->assertRedirect();
        $this->actingAs($occupant)->get($tab)->assertSee('Handover until');
        $this->actingAs($master)->get(route('school-management.show', $toSchool))->assertSee('Handover until')->assertSee('End handover now');
    }

    public function test_a_pending_review_blocks_the_approve_button(): void
    {
        [, , , , $toSchool] = $this->occupiedRequest();

        $this->actingAs($this->master())->get(route('school-management.show', $toSchool))->assertOk()->assertSee('Waiting for '.$toSchool->name)->assertDontSee('Approve</button>', false);
    }
}
