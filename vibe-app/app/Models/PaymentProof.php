<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** A school's proof of payment: the file stays in the school's own Google Drive and is shared with the master's Google email; only its pointer is kept here. */
class PaymentProof extends Model
{
    use BelongsToOrganization;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = ['organization_id', 'school_id', 'user_id', 'subscription_id', 'reference', 'paid_at', 'list_price', 'discount_amount', 'promo_code_id', 'amount_to_pay', 'drive_file_id', 'drive_link', 'file_name', 'mime', 'size', 'sha256', 'status', 'reviewed_by', 'reviewed_at', 'reject_reason'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'list_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'amount_to_pay' => 'decimal:2', 'reviewed_at' => 'datetime', 'size' => 'integer'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
