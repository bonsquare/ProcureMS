<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class AuditLog extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'user_id', 'school_id', 'action', 'auditable_type', 'auditable_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function school() { return $this->belongsTo(School::class); }
    public function auditable() { return $this->morphTo(); }
}
