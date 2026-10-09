<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationTransferRequest extends Model
{
    protected $fillable = [
        'user_id', 'from_school_id', 'from_organization_id', 'to_school_id', 'proposed_school', 'reason',
        'status', 'requested_at', 'decided_by', 'decided_at', 'decision_note', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_school' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'confirmed_at' => 'datetime',
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

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function destinationName(): string
    {
        return $this->toSchool?->name ?? ($this->proposed_school['name'] ?? '—');
    }
}
