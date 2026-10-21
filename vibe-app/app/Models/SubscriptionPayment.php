<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One payment of a school's subscription, with the price it was charged; the income of the dashboard adds up `amount`. */
class SubscriptionPayment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'subscription_id', 'amount', 'list_price', 'discount_amount', 'promo_code_id', 'reference', 'paid_at', 'period_start', 'period_end', 'method', 'recorded_by', 'proof_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'list_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'paid_at' => 'date', 'period_start' => 'date', 'period_end' => 'date'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function proof()
    {
        return $this->belongsTo(PaymentProof::class, 'proof_id');
    }
}
