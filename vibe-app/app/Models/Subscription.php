<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToOrganization, HasFactory;

    public const TRANSACTIONAL_STATUSES = ['trial', 'active', 'grace_period', 'free'];

    protected $fillable = ['organization_id', 'school_id', 'user_id', 'plan', 'billing_cycle', 'amount', 'charge_amount', 'charge_ends_at', 'previous_state', 'payment_status', 'status', 'starts_at', 'renews_at', 'subscription_end', 'grace_period_end', 'renewed_at', 'canceled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'charge_amount' => 'decimal:2', 'charge_ends_at' => 'date', 'previous_state' => 'array', 'starts_at' => 'date', 'renews_at' => 'date', 'subscription_end' => 'date', 'grace_period_end' => 'date', 'renewed_at' => 'datetime', 'canceled_at' => 'date'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /** The trial is the limited state: it cannot print or record a payment. Free is the full system. */
    public function isTrial(): bool
    {
        return strtolower((string) $this->status) === 'trial';
    }

    /** Limited like the trial: in the trial, or on a plan that was never paid (a trial that ran out, or Free taken back from a school that never paid). */
    public function isLimited(): bool
    {
        return $this->isTrial() || strtolower((string) $this->plan) === 'trial';
    }

    public function isFree(): bool
    {
        return strtolower((string) $this->status) === 'free';
    }

    public function allowsTransactions(): bool
    {
        $status = strtolower($this->status);
        if (! in_array($status, self::TRANSACTIONAL_STATUSES, true)) {
            return false;
        }

        if ($status === 'grace_period') {
            return ! $this->grace_period_end || $this->grace_period_end->isFuture() || $this->grace_period_end->isToday();
        }

        return ! $this->subscription_end || $this->subscription_end->isFuture() || $this->subscription_end->isToday();
    }
}
