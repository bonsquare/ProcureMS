<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToOrganization, HasFactory;

    public const TRANSACTIONAL_STATUSES = ['trial', 'active', 'grace_period'];

    protected $fillable = ['organization_id', 'school_id', 'plan', 'billing_cycle', 'amount', 'payment_status', 'status', 'starts_at', 'renews_at', 'subscription_end', 'grace_period_end', 'renewed_at', 'canceled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_at' => 'date', 'renews_at' => 'date', 'subscription_end' => 'date', 'grace_period_end' => 'date', 'renewed_at' => 'datetime', 'canceled_at' => 'date'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
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
