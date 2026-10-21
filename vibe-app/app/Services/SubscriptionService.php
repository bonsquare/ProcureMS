<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change of a school's subscription goes through here. The rows of a school (one for each of its users) always carry the same state,
 * so a change is applied to all of them; a user added later joins the state the school is in. Each action is written to the audit log with its reason.
 */
class SubscriptionService
{
    public const TRIAL_DAYS = 3;

    public const GRACE_DAYS = 7;

    public const ANNUAL_DAYS = 365;

    /** The trial of a school: only one, ever. A school that already has a subscription gives a new user its current state instead. */
    public function startTrial(School $school, User $owner): Subscription
    {
        $current = $this->rows($school)->sortByDesc('id')->first();
        if ($current) {
            return $this->rows($school)->firstWhere('user_id', $owner->id) ?? $this->join($current, $owner);
        }
        $this->assertNoTwinTrial($school);

        return Subscription::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $owner->id,
            'plan' => 'trial', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'pending', 'status' => 'trial',
            'starts_at' => now(), 'subscription_end' => now()->addDays(self::TRIAL_DAYS),
        ]);
    }

    /** The trial counts from the day the master activates the school, not from the pre-registration. */
    public function beginTrialOnActivation(School $school): void
    {
        Subscription::withoutGlobalScopes()->where('school_id', $school->id)->where('status', 'trial')
            ->update(['starts_at' => now()->toDateString(), 'subscription_end' => now()->addDays(self::TRIAL_DAYS)->toDateString()]);
    }

    /** A payment (the amount the school really paid) makes the school Annual: a year more from the end it still has, or from today. */
    public function activateAnnual(School $school, array $payment, User $by): SubscriptionPayment
    {
        $this->assertStaff($by);

        return DB::transaction(function () use ($school, $payment, $by) {
            $rows = $this->lockedRows($school);
            $current = $rows->sortByDesc('id')->first();
            $renewing = $current && $current->plan === 'annual' && in_array($current->status, ['active', 'grace_period'], true)
                && $current->subscription_end && $current->subscription_end->startOfDay()->gte(now()->startOfDay());
            $start = $renewing ? $current->subscription_end->copy()->startOfDay() : now()->startOfDay();
            $end = $start->copy()->addDays(self::ANNUAL_DAYS);

            $fields = ['plan' => 'annual', 'billing_cycle' => 'annual', 'amount' => $payment['amount'], 'payment_status' => 'paid', 'status' => 'active',
                'renews_at' => $end->toDateString(), 'subscription_end' => $end->toDateString(), 'grace_period_end' => null, 'canceled_at' => null, 'renewed_at' => now(), 'previous_state' => null];
            if (! $renewing) {
                $fields['starts_at'] = now()->toDateString();
            }
            $rows->isEmpty() ? $this->createRow($school, $fields + ['starts_at' => now()->toDateString()]) : $this->updateAll($school, $fields);
            $row = $this->rows($school)->sortByDesc('id')->first();

            $record = SubscriptionPayment::withoutGlobalScopes()->create([
                'organization_id' => $school->organization_id, 'school_id' => $school->id, 'subscription_id' => $row->id,
                'amount' => $payment['amount'], 'list_price' => $payment['list_price'] ?? SubscriptionPricing::LIST_PRICE, 'discount_amount' => $payment['discount_amount'] ?? 0,
                'promo_code_id' => $payment['promo_code_id'] ?? null, 'reference' => $payment['reference'] ?? null, 'paid_at' => $payment['paid_at'] ?? now()->toDateString(),
                'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'method' => $payment['method'] ?? 'bank_transfer', 'recorded_by' => $by->id, 'proof_id' => $payment['proof_id'] ?? null,
            ]);
            $this->audit($school, $by, $renewing ? 'subscription_renewed' : 'subscription_activated', $payment['reason'] ?? null, ['amount' => (string) $payment['amount'], 'until' => $end->toDateString()]);

            return $record;
        });
    }

    /** The Super admin gives a school the full system until a date he or she sets. */
    public function grantFree(School $school, CarbonInterface $endsAt, User $by, string $reason): Subscription
    {
        $this->assertMaster($by);
        $this->assertFutureDate($endsAt);

        return DB::transaction(function () use ($school, $endsAt, $by, $reason) {
            $rows = $this->lockedRows($school);
            if ($rows->isEmpty()) {
                $this->createRow($school, ['plan' => 'free', 'status' => 'free', 'starts_at' => now()->toDateString(), 'subscription_end' => $endsAt->toDateString()]);
            }
            foreach ($rows as $row) {
                $state = $row->previous_state ?? [];
                $state['before_free'] ??= $row->status === 'free' ? null : $this->snapshot($row);
                $row->update(['plan' => 'free', 'billing_cycle' => 'annual', 'payment_status' => 'waived', 'status' => 'free', 'subscription_end' => $endsAt->toDateString(), 'grace_period_end' => null, 'previous_state' => $state]);
            }
            $this->audit($school, $by, 'subscription_free_granted', $reason, ['until' => $endsAt->toDateString()]);

            return $this->rows($school)->sortByDesc('id')->first();
        });
    }

    public function changeFree(School $school, CarbonInterface $endsAt, User $by, string $reason): void
    {
        $this->assertMaster($by);
        $this->assertFutureDate($endsAt);
        DB::transaction(function () use ($school, $endsAt, $by, $reason) {
            $this->updateAll($school, ['subscription_end' => $endsAt->toDateString(), 'status' => 'free'], 'free');
            $this->audit($school, $by, 'subscription_free_changed', $reason, ['until' => $endsAt->toDateString()]);
        });
    }

    /** Takes the Free plan back: the school returns to the state it had before (read-only when it had none). */
    public function removeFree(School $school, User $by, string $reason): void
    {
        $this->assertMaster($by);
        DB::transaction(function () use ($school, $by, $reason) {
            foreach ($this->lockedRows($school)->where('status', 'free') as $row) {
                $before = ($row->previous_state ?? [])['before_free'] ?? null;
                $state = $row->previous_state ?? [];
                unset($state['before_free']);
                $row->update(($before ?: ['plan' => 'trial', 'status' => 'expired', 'payment_status' => 'pending', 'subscription_end' => now()->subDay()->toDateString(), 'grace_period_end' => null]) + ['previous_state' => $state ?: null]);
            }
            $this->audit($school, $by, 'subscription_free_removed', $reason);
        });
    }

    public function suspend(School $school, User $by, string $reason): void
    {
        $this->assertStaff($by);
        DB::transaction(function () use ($school, $by, $reason) {
            foreach ($this->lockedRows($school)->where('status', '!=', 'suspended') as $row) {
                $state = $row->previous_state ?? [];
                $state['before_suspend'] = $row->status;
                $row->update(['status' => 'suspended', 'previous_state' => $state]);
            }
            $this->audit($school, $by, 'subscription_suspended', $reason);
        });
    }

    public function reactivate(School $school, User $by, string $reason): void
    {
        $this->assertStaff($by);
        DB::transaction(function () use ($school, $by, $reason) {
            foreach ($this->lockedRows($school)->where('status', 'suspended') as $row) {
                $state = $row->previous_state ?? [];
                $back = $state['before_suspend'] ?? 'active';
                unset($state['before_suspend']);
                $row->update(['status' => $back, 'previous_state' => $state ?: null]);
            }
            $this->audit($school, $by, 'subscription_reactivated', $reason);
        });
    }

    /** Extra days for a trial, a Free plan or an Annual that is running or in its grace; an expired school gets the days as grace. */
    public function extend(School $school, int $days, User $by, string $reason): void
    {
        $this->assertStaff($by);
        throw_if($days < 1 || $days > 365, ValidationException::withMessages(['days' => 'Give 1 to 365 days.']));
        DB::transaction(function () use ($school, $days, $by, $reason) {
            foreach ($this->lockedRows($school) as $row) {
                $today = now()->startOfDay();
                if ($row->status === 'grace_period') {
                    $row->update(['grace_period_end' => ($row->grace_period_end && $row->grace_period_end->gte($today) ? $row->grace_period_end : $today)->copy()->addDays($days)->toDateString()]);
                } elseif ($row->status === 'expired') {
                    $row->update(['status' => 'grace_period', 'grace_period_end' => $today->copy()->addDays($days)->toDateString()]);
                } elseif (in_array($row->status, ['trial', 'free', 'active'], true)) {
                    $end = $row->subscription_end && $row->subscription_end->gte($today) ? $row->subscription_end : $today;
                    $row->update(['subscription_end' => $end->copy()->addDays($days)->toDateString()]);
                }
            }
            $this->audit($school, $by, 'subscription_extended', $reason, ['days' => $days]);
        });
    }

    /** What the school pays for the Annual plan (the master decides); null goes back to the list price. */
    public function setCharge(School $school, ?float $amount, ?CarbonInterface $endsAt, User $by, string $reason): void
    {
        $this->assertMaster($by);
        throw_if($amount !== null && ($amount < SubscriptionPricing::MINIMUM || $amount > SubscriptionPricing::LIST_PRICE), ValidationException::withMessages(['amount' => 'The price must be from 1.00 to 1,000.00.']));
        DB::transaction(function () use ($school, $amount, $endsAt, $by, $reason) {
            $rows = $this->lockedRows($school);
            $rows->isEmpty() ? $this->createRow($school, ['plan' => 'trial', 'status' => 'expired', 'starts_at' => now()->toDateString(), 'subscription_end' => now()->subDay()->toDateString()]) : null;
            $this->updateAll($school, ['charge_amount' => $amount, 'charge_ends_at' => $amount === null ? null : $endsAt?->toDateString()]);
            $this->audit($school, $by, 'subscription_price_set', $reason, ['amount' => $amount === null ? null : (string) $amount, 'until' => $amount === null ? null : $endsAt?->toDateString()]);
        });
    }

    /** The daily run: the states follow the dates. The write guard also reads the dates, so a missed run never gives free access. @return array{trial: int, grace: int, expired: int} */
    public function sync(): array
    {
        $today = now()->toDateString();
        $query = fn (array $statuses, string $column) => Subscription::withoutGlobalScopes()->whereIn('status', $statuses)->whereNotNull($column)->whereDate($column, '<', $today);

        $trial = $query(['trial'], 'subscription_end')->update(['status' => 'expired']);
        $free = $query(['free'], 'subscription_end')->update(['status' => 'expired']);
        $grace = 0;
        foreach ($query(['active'], 'subscription_end')->get() as $row) {
            $row->update(['status' => 'grace_period', 'grace_period_end' => $row->subscription_end->copy()->addDays(self::GRACE_DAYS)->toDateString()]);
            $grace++;
        }
        $expired = $query(['grace_period'], 'grace_period_end')->update(['status' => 'expired']) + $free;

        return ['trial' => $trial, 'grace' => $grace, 'expired' => $expired];
    }

    /** @return Collection<int, Subscription> */
    private function rows(School $school): Collection
    {
        return Subscription::withoutGlobalScopes()->where('school_id', $school->id)->get();
    }

    /** @return Collection<int, Subscription> */
    private function lockedRows(School $school): Collection
    {
        return Subscription::withoutGlobalScopes()->where('school_id', $school->id)->lockForUpdate()->get();
    }

    private function updateAll(School $school, array $fields, ?string $onlyStatus = null): void
    {
        Subscription::withoutGlobalScopes()->where('school_id', $school->id)->when($onlyStatus, fn ($query, $status) => $query->where('status', $status))->get()->each->update($fields);
    }

    private function createRow(School $school, array $fields): Subscription
    {
        $owner = User::withoutGlobalScopes()->where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)->oldest('id')->first();

        return Subscription::create($fields + ['organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $owner?->id, 'plan' => 'annual', 'billing_cycle' => 'annual', 'amount' => 0, 'payment_status' => 'pending', 'status' => 'active']);
    }

    private function join(Subscription $current, User $owner): Subscription
    {
        return Subscription::create($current->only(['organization_id', 'school_id', 'plan', 'billing_cycle', 'amount', 'charge_amount', 'charge_ends_at', 'payment_status', 'status', 'starts_at', 'renews_at', 'subscription_end', 'grace_period_end', 'previous_state']) + ['user_id' => $owner->id]);
    }

    private function assertNoTwinTrial(School $school): void
    {
        if (! filled($school->deped_school_id)) {
            return;
        }
        $twin = School::withoutGlobalScopes()->where('deped_school_id', $school->deped_school_id)->where('id', '!=', $school->id)
            ->whereHas('subscriptions', fn ($query) => $query->withoutGlobalScopes())->exists();
        throw_if($twin, ValidationException::withMessages(['school' => 'A school with this DepEd School ID already had its trial.']));
    }

    private function snapshot(Subscription $row): array
    {
        return [
            'plan' => $row->plan, 'billing_cycle' => $row->billing_cycle, 'amount' => (string) $row->amount, 'payment_status' => $row->payment_status, 'status' => $row->status,
            'subscription_end' => $row->subscription_end?->toDateString(), 'grace_period_end' => $row->grace_period_end?->toDateString(),
        ];
    }

    private function assertFutureDate(CarbonInterface $endsAt): void
    {
        throw_if($endsAt->copy()->startOfDay()->lt(now()->startOfDay()), ValidationException::withMessages(['ends_at' => 'The end date must be today or later.']));
    }

    private function assertMaster(User $by): void
    {
        throw_unless($by->isMaster(), ValidationException::withMessages(['actor' => 'Only the Super admin can do this.']));
    }

    private function assertStaff(User $by): void
    {
        throw_unless($by->isMaster() || $by->isSubMaster(), ValidationException::withMessages(['actor' => 'Only the master or a sub-master can do this.']));
    }

    private function audit(School $school, ?User $by, string $action, ?string $reason, array $meta = []): void
    {
        AuditLog::create(['user_id' => $by?->id, 'school_id' => $school->id, 'action' => $action, 'auditable_type' => School::class, 'auditable_id' => $school->id, 'metadata' => ['reason' => $reason] + $meta]);
    }
}
