<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PaymentProof;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The findings of the final review of the subscription system, each pinned by a test that failed first. */
class SubscriptionReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionService::class);
        $this->master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->travelTo('2026-10-21 10:00:00');
    }

    private function school(string $code, ?string $status = null, string $plan = 'annual', int $daysLeft = 100): array
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        if ($status) {
            Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => $plan, 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'pending', 'status' => $status, 'starts_at' => now()->subDays(5), 'subscription_end' => now()->addDays($daysLeft)]);
        }

        return [$school, $user];
    }

    private function proof(School $school, User $user, string $sha, string $status = 'pending'): PaymentProof
    {
        return PaymentProof::withoutGlobalScopes()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $user->id, 'reference' => 'R-'.substr($sha, 0, 3), 'paid_at' => now()->toDateString(), 'list_price' => 1000, 'discount_amount' => 0, 'amount_to_pay' => 1000, 'drive_file_id' => 'f', 'drive_link' => 'https://drive.google.com/file/d/f/view', 'file_name' => 'p.png', 'mime' => 'image/png', 'size' => 1, 'sha256' => $sha, 'status' => $status]);
    }

    private function asMaster()
    {
        auth()->logout();

        return $this->actingAs($this->master);
    }

    public function test_a_trial_that_ran_out_still_cannot_print(): void
    {
        [, $user] = $this->school('R1', 'expired', 'trial', -2);

        $this->actingAs($user)->get(route('cash.report.roci'))->assertStatus(402);
    }

    public function test_after_taking_free_back_a_school_that_never_paid_still_cannot_print(): void
    {
        [$school, $user] = $this->school('R2', 'trial', 'trial', 2);
        $this->service->grantFree($school, now()->addDays(10), $this->master, 'Pilot');
        $this->service->removeFree($school, $this->master, 'Over');

        $this->actingAs($user)->get(route('cash.report.roci'))->assertStatus(402);
    }

    public function test_a_school_that_paid_prints_even_when_it_is_read_only(): void
    {
        [, $user] = $this->school('R3', 'expired', 'annual', -40);

        $this->actingAs($user)->get(route('cash.report.roci'))->assertSessionHasErrors('school_id');
    }

    public function test_the_same_file_cannot_be_approved_as_proof_twice(): void
    {
        [$school, $user] = $this->school('R4', 'trial', 'trial', 2);
        $first = $this->proof($school, $user, str_repeat('c', 64));
        $second = $this->proof($school, $user, str_repeat('c', 64));

        $this->asMaster()->post(route('subscriptions.proofs.approve', $first), ['reason' => 'ok', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('subscriptions.proofs.approve', $second), ['reason' => 'ok', 'password' => 'password'])->assertSessionHasErrors('proof');

        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_what_is_done_to_a_school_does_not_lock_a_user_who_left_it(): void
    {
        [$old, $mover] = $this->school('R5', 'active', 'annual', 300);
        [$new] = $this->school('R6', 'active', 'annual', 300);
        $mover->forceFill(['school_id' => $new->id, 'organization_id' => $new->organization_id])->save();

        $this->service->suspend($old, $this->master, 'Checked');
        $this->service->extend($old, 3, $this->master, 'More');

        $this->assertSame('active', $mover->fresh()->activeSubscription()->status, 'the personal subscription of the user who left is not suspended');
    }

    public function test_free_that_ends_gives_back_the_annual_that_was_paid(): void
    {
        [$school, $user] = $this->school('R7', 'trial', 'trial', 2);
        $this->service->activateAnnual($school, ['amount' => 1000, 'list_price' => 1000, 'discount_amount' => 0, 'reference' => 'R', 'paid_at' => now()->toDateString()], $this->master);
        $this->service->grantFree($school, now()->addDays(10), $this->master, 'Event');

        $this->travelTo('2026-11-05 01:00:00');
        $this->service->sync();

        $row = Subscription::withoutGlobalScopes()->where('school_id', $school->id)->latest('id')->first();
        $this->assertSame(['annual', 'active', '2027-10-21'], [$row->plan, $row->status, $row->subscription_end->toDateString()]);
        $this->assertTrue($row->allowsTransactions());
        $this->assertNotNull($user);
    }

    public function test_free_that_ends_without_a_paid_plan_becomes_read_only(): void
    {
        [$school] = $this->school('R8', 'trial', 'trial', 2);
        $this->service->grantFree($school, now()->addDays(10), $this->master, 'Event');

        $this->travelTo('2026-11-05 01:00:00');
        $this->service->sync();

        $this->assertSame('expired', Subscription::withoutGlobalScopes()->where('school_id', $school->id)->latest('id')->first()->status);
    }

    public function test_activate_is_refused_while_a_proof_waits(): void
    {
        [$school, $user] = $this->school('R9', 'trial', 'trial', 2);
        $this->proof($school, $user, str_repeat('d', 64));

        $this->asMaster()->post(route('subscriptions.activate', $school), ['reason' => 'x', 'password' => 'password', 'reference' => 'CASH', 'paid_at' => now()->toDateString()])->assertSessionHasErrors('proof');

        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count());
    }

    public function test_create_trial_is_refused_for_a_school_that_has_a_subscription(): void
    {
        [$school] = $this->school('R10', 'trial', 'trial', 1);

        $this->asMaster()->post(route('subscriptions.store'), ['school_id' => $school->id, 'mode' => 'trial', 'reason' => 'x', 'password' => 'password'])->assertSessionHasErrors('school_id');

        $this->assertSame(now()->addDay()->toDateString(), Subscription::withoutGlobalScopes()->where('school_id', $school->id)->first()->subscription_end->toDateString(), 'the trial was not restarted');
    }

    public function test_a_price_cannot_be_set_for_a_school_without_a_subscription(): void
    {
        [$school] = $this->school('R11');

        $this->expectException(ValidationException::class);
        $this->service->setCharge($school, 600.0, null, $this->master, 'Partner');
    }

    public function test_extend_is_refused_on_a_suspended_school(): void
    {
        [$school] = $this->school('R12', 'trial', 'trial', 2);
        $this->service->suspend($school, $this->master, 'Checked');

        try {
            $this->service->extend($school, 5, $this->master, 'More');
            $this->fail('a suspended school cannot be extended');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('days', $exception->errors());
        }
        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('action', 'subscription_extended')->count());
    }

    public function test_the_print_and_export_pages_are_blocked_in_the_trial(): void
    {
        [, $user] = $this->school('R13', 'trial', 'trial', 2);
        $this->actingAs($user);

        $this->get(route('dashboard.audit-logs.export'))->assertStatus(402);
        $this->get(route('budget.allocation.report', ['type' => 'allotment']).'?print=1')->assertStatus(402);
        $this->get(route('budget.allocation.report', ['type' => 'allotment']).'?format=csv')->assertStatus(402);
        $this->assertNotSame(402, $this->get(route('budget.allocation.report', ['type' => 'allotment']))->getStatusCode(), 'the page itself can be read');
    }
}
