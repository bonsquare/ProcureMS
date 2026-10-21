<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    private User $master;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $organization = Organization::create(['name' => 'Lc', 'slug' => 'lc', 'status' => 'active']);
        $this->school = School::create(['organization_id' => $organization->id, 'code' => 'LC1', 'name' => 'Life School', 'status' => 'active']);
        $this->owner = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        $this->master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->service = app(SubscriptionService::class);
        $this->travelTo('2026-10-21 10:00:00');
    }

    private function current(): Subscription
    {
        return Subscription::withoutGlobalScopes()->where('school_id', $this->school->id)->latest('id')->firstOrFail();
    }

    private function payment(float $amount = 1000): array
    {
        return ['amount' => $amount, 'list_price' => 1000.0, 'discount_amount' => 1000.0 - $amount, 'promo_code_id' => null, 'reference' => 'REF-1', 'paid_at' => now()->toDateString(), 'proof_id' => null];
    }

    public function test_a_new_trial_lasts_three_days(): void
    {
        $trial = $this->service->startTrial($this->school, $this->owner);

        $this->assertSame(['trial', 'trial', '2026-10-24'], [$trial->status, $trial->plan, $trial->subscription_end->toDateString()]);
        $this->assertTrue($trial->allowsTransactions());
    }

    public function test_a_school_gets_one_trial_only_and_a_new_user_joins_its_state(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->travelTo('2026-11-01 10:00:00');
        $this->service->sync();
        $second = User::factory()->create(['organization_id' => $this->school->organization_id, 'school_id' => $this->school->id, 'role' => 'school_admin']);

        $joined = $this->service->startTrial($this->school, $second);

        $this->assertSame('expired', $joined->status, 'the school does not get a second trial');
        $this->assertSame($second->id, $joined->user_id);
        $this->assertFalse($joined->allowsTransactions());
    }

    public function test_the_same_deped_school_id_cannot_start_another_trial(): void
    {
        $this->school->update(['deped_school_id' => '130059']);
        $this->service->startTrial($this->school, $this->owner);
        $other = School::create(['organization_id' => $this->school->organization_id, 'code' => 'LC2', 'name' => 'Twin School', 'deped_school_id' => '130059', 'status' => 'active']);

        $this->expectException(ValidationException::class);
        $this->service->startTrial($other, $this->owner);
    }

    public function test_the_trial_begins_when_the_school_is_activated(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->travelTo('2026-10-30 08:00:00');

        $this->service->beginTrialOnActivation($this->school);

        $this->assertSame('2026-11-02', $this->current()->subscription_end->toDateString());
    }

    public function test_the_existing_trial_keeps_its_end_date(): void
    {
        Subscription::create(['organization_id' => $this->school->organization_id, 'school_id' => $this->school->id, 'user_id' => $this->owner->id, 'plan' => 'trial', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'pending', 'status' => 'trial', 'starts_at' => '2026-10-10', 'subscription_end' => '2026-11-09']);

        $this->service->sync();

        $this->assertSame(['trial', '2026-11-09'], [$this->current()->status, $this->current()->subscription_end->toDateString()]);
    }

    public function test_free_needs_an_end_date_and_only_the_master_gives_it(): void
    {
        $this->service->startTrial($this->school, $this->owner);

        foreach ([now()->subDay(), now()->startOfDay()->subSecond()] as $past) {
            try {
                $this->service->grantFree($this->school, $past, $this->master, 'Pilot');
                $this->fail('a past date is refused');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('ends_at', $exception->errors());
            }
        }
        try {
            $this->service->grantFree($this->school, now()->addMonth(), $this->owner, 'Self');
            $this->fail('a school cannot give itself Free');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }

        $free = $this->service->grantFree($this->school, now()->addMonth(), $this->master, 'Pilot school');
        $this->assertSame(['free', '2026-11-21'], [$free->status, $free->subscription_end->toDateString()]);
    }

    public function test_free_has_the_full_system_and_becomes_read_only_after_its_date(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->service->grantFree($this->school, now()->addDays(10), $this->master, 'Pilot');
        $this->assertTrue($this->current()->allowsTransactions());
        $this->assertFalse($this->current()->isTrial(), 'Free is not limited like the trial');

        $this->travelTo('2026-10-31 23:00:00');
        $this->assertTrue($this->current()->allowsTransactions(), 'the end date itself still works');

        $this->travelTo('2026-11-01 00:10:00');
        $this->assertFalse($this->current()->allowsTransactions(), 'a missed sync never gives free access');
        $this->service->sync();
        $this->assertSame('expired', $this->current()->status);
    }

    public function test_the_master_changes_and_removes_free_and_the_school_returns_to_its_state(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->service->activateAnnual($this->school, $this->payment(), $this->master);
        $this->service->grantFree($this->school, now()->addDays(5), $this->master, 'Event');

        $this->service->changeFree($this->school, now()->addDays(20), $this->master, 'Longer event');
        $this->assertSame('2026-11-10', $this->current()->subscription_end->toDateString());

        $this->service->removeFree($this->school, $this->master, 'Event over');
        $this->assertSame(['active', '2027-10-21'], [$this->current()->status, $this->current()->subscription_end->toDateString()], 'back to the Annual it had');
    }

    public function test_annual_runs_365_days_then_grace_7_days_then_read_only(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $payment = $this->service->activateAnnual($this->school, $this->payment(750), $this->master);

        $this->assertInstanceOf(SubscriptionPayment::class, $payment);
        $this->assertSame(['annual', 'active', '2027-10-21'], [$this->current()->plan, $this->current()->status, $this->current()->subscription_end->toDateString()]);
        $this->assertSame(['750.00', '250.00', '2026-10-21', '2027-10-21'], [$payment->amount, $payment->discount_amount, $payment->period_start->toDateString(), $payment->period_end->toDateString()]);

        $this->travelTo('2027-10-22 00:30:00');
        $this->service->sync();
        $this->assertSame(['grace_period', '2027-10-28'], [$this->current()->status, $this->current()->grace_period_end->toDateString()]);
        $this->assertTrue($this->current()->allowsTransactions());

        $this->travelTo('2027-10-29 00:30:00');
        $this->service->sync();
        $this->assertSame('expired', $this->current()->status);
        $this->assertFalse($this->current()->allowsTransactions());
    }

    public function test_renewing_adds_a_year_to_the_end_or_to_today(): void
    {
        $this->service->activateAnnual($this->school, $this->payment(), $this->master);
        $this->travelTo('2027-06-01 10:00:00');
        $this->service->activateAnnual($this->school, $this->payment(), $this->master);
        $this->assertSame('2028-10-20', $this->current()->subscription_end->toDateString(), '365 days added to the end date that was still running (2028 is a leap year)');

        $this->travelTo('2029-03-01 10:00:00');
        $this->service->sync();
        $this->service->activateAnnual($this->school, $this->payment(), $this->master);
        $this->assertSame('2030-03-01', $this->current()->subscription_end->toDateString(), 'added to today when it had ended');
    }

    public function test_suspend_reactivate_and_extend(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->service->suspend($this->school, $this->master, 'Unpaid');
        $this->assertFalse($this->current()->allowsTransactions());

        $this->service->reactivate($this->school, $this->master, 'Paid in cash');
        $this->assertTrue($this->current()->allowsTransactions());

        $this->service->extend($this->school, 2, $this->master, 'More time');
        $this->assertSame('2026-10-26', $this->current()->subscription_end->toDateString());
    }

    public function test_the_price_set_for_a_school_is_kept_on_all_its_rows(): void
    {
        $this->service->startTrial($this->school, $this->owner);

        $this->service->setCharge($this->school, 600.0, now()->addMonths(2), $this->master, 'Partner school');

        $this->assertSame(['600.00', '2026-12-21'], [$this->current()->charge_amount, $this->current()->charge_ends_at->toDateString()]);
        $this->service->setCharge($this->school, null, null, $this->master, 'Back to list');
        $this->assertNull($this->current()->charge_amount);
    }

    public function test_every_action_writes_the_audit_log_with_the_reason(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->service->grantFree($this->school, now()->addMonth(), $this->master, 'Pilot school');
        $this->service->suspend($this->school, $this->master, 'Checked');

        $logs = AuditLog::withoutGlobalScopes()->where('school_id', $this->school->id)->where('action', 'like', 'subscription_%')->orderBy('id')->get();

        $this->assertSame(['subscription_free_granted', 'subscription_suspended'], $logs->pluck('action')->all());
        $this->assertSame(['Pilot school', 'Checked'], $logs->map(fn ($log) => $log->metadata['reason'])->all());
    }

    public function test_sync_reports_what_it_moved(): void
    {
        $this->service->startTrial($this->school, $this->owner);
        $this->travelTo('2026-10-25 00:10:00');

        $this->assertSame(['trial' => 1, 'grace' => 0, 'expired' => 0], $this->service->sync());
        $this->assertSame(['trial' => 0, 'grace' => 0, 'expired' => 0], $this->service->sync());
    }
}
