<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTakeoverRequest extends Model
{
    protected $fillable = ['user_id', 'school_id', 'status', 'note', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function school()
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
