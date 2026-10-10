<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenericRequestTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    /** @return array{0: School, 1: ?User} */
    private function school(bool $withUser = true): array
    {
        $n = ++$this->n;
        $organization = Organization::create(['name' => 'Org '.$n, 'slug' => 'org-'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'S'.$n, 'name' => 'School '.$n, 'status' => 'active']);
        $user = null;
        if ($withUser) {
            Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
            $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id, 'status' => 'active']);
        }

        return [$school, $user];
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user']);
    }

    private function tab(): string
    {
        return route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']);
    }

    public function test_the_form_is_a_generic_request_with_the_new_types(): void
    {
        [, $user] = $this->school();

        $this->actingAs($user)->get($this->tab())->assertOk()
            ->assertSee('Make a request')->assertDontSee('Request a station transfer')
            ->assertSee('Request to Official Station')->assertSee('>Other<', false)->assertSee('name="subject"', false);
    }

    public function test_an_official_station_request_is_approved_with_a_school_the_master_chooses(): void
    {
        [$from, $user] = $this->school();
        [$vacant] = $this->school(withUser: false);
        $master = $this->master();

        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'official_station', 'reason' => 'Please assign me'])->assertRedirect()->assertSessionHasNoErrors();
        $request = StationTransferRequest::firstOrFail();
        $this->assertSame(['official_station', 'pending', 'not_required', null], [$request->kind, $request->status, $request->review_status, $request->to_school_id]);

        $this->actingAs($master)->get(route('transfer-requests'))->assertOk()->assertSee('Asks to be given an Official Station')->assertSee('name="school_id"', false)->assertSee($vacant->name);

        $this->actingAs($master)->post(route('transfer-requests.approve', $request), [])->assertSessionHasErrors('school_id');
        $this->actingAs($master)->post(route('transfer-requests.approve', $request), ['school_id' => $from->id])->assertSessionHasErrors('school_id');
        $this->assertSame($from->id, $user->fresh()->school_id);

        $this->actingAs($master)->post(route('transfer-requests.approve', $request), ['school_id' => $vacant->id])->assertSessionHasNoErrors();
        $this->assertSame([$vacant->id, 'approved'], [$user->fresh()->school_id, $request->fresh()->status]);
    }

    public function test_an_other_request_needs_a_typed_subject_and_the_master_only_answers_it(): void
    {
        [$school, $user] = $this->school();
        $master = $this->master();

        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'other', 'reason' => 'Details'])->assertSessionHasErrors('subject');
        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'other', 'subject' => 'Change of office', 'reason' => 'Moved to the supply office'])->assertSessionHasNoErrors();
        $request = StationTransferRequest::firstOrFail();

        $this->actingAs($user)->get($this->tab())->assertOk()->assertSee('Change of office')->assertSee('Waiting for the master user to decide.');
        $this->actingAs($master)->get(route('transfer-requests'))->assertSee('Change of office')->assertSee('Moved to the supply office');

        $this->actingAs($master)->post(route('transfer-requests.approve', $request), ['decision_note' => 'Noted'])->assertSessionHasNoErrors();
        $this->assertSame([$school->id, 'approved'], [$user->fresh()->school_id, $request->fresh()->status]);
        $this->assertNotNull($request->fresh()->confirmed_at, 'nothing to confirm for a typed request');
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_one_pending_request_at_a_time_and_other_requests_stay_out_of_school_management(): void
    {
        [$school, $user] = $this->school();
        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'other', 'subject' => 'First', 'reason' => 'x'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'other', 'subject' => 'Second', 'reason' => 'y'])->assertSessionHasErrors('reason');

        $this->actingAs($this->master())->get(route('school-management.show', $school->id))->assertOk()->assertDontSee('Transfer request');
    }
}
