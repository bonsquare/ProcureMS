<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationTransferRequest extends Model
{
    protected $fillable = [
        'user_id', 'kind', 'subject', 'from_school_id', 'from_organization_id', 'to_school_id', 'proposed_school', 'reason',
        'status', 'requested_at', 'decided_by', 'decided_at', 'decision_note', 'confirmed_at',
        'review_status', 'reviewer_user_id', 'reviewed_at', 'review_note', 'review_expires_at', 'expired_at',
        'handover_ends_at', 'handover_user_id', 'handover_ended_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_school' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'handover_ends_at' => 'datetime',
            'handover_ended_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function fromSchool()
    {
        return $this->belongsTo(School::class, 'from_school_id')->withoutGlobalScopes();
    }

    public function toSchool()
    {
        return $this->belongsTo(School::class, 'to_school_id')->withoutGlobalScopes();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_user_id')->withoutGlobalScopes();
    }

    public function handoverUser()
    {
        return $this->belongsTo(User::class, 'handover_user_id')->withoutGlobalScopes();
    }

    /** The master can approve only when the destination school has accepted (or had nobody to ask). */
    public function canBeApproved(): bool
    {
        return $this->isPending() && in_array($this->review_status, ['not_required', 'accepted'], true);
    }

    /** Approved, with the previous user of the destination school still inside the handover period. */
    public function handoverRunning(): bool
    {
        return $this->status === 'approved' && $this->handover_user_id && ! $this->handover_ended_at;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public const KINDS = ['transfer' => 'Transfer', 'official_station' => 'Official Station', 'other' => 'Other'];

    public function isTransfer(): bool
    {
        return ($this->kind ?: 'transfer') === 'transfer';
    }

    /** What the request is about: the school it asks for, or the typed subject. */
    public function destinationName(): string
    {
        return match ($this->kind) {
            'official_station' => $this->toSchool?->name ?? 'an Official Station',
            'other' => $this->subject ?: 'Other request',
            default => $this->toSchool?->name ?? ($this->proposed_school['name'] ?? '—'),
        };
    }
}
