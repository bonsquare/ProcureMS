<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class Subscription extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'plan', 'billing_cycle', 'amount', 'status', 'starts_at', 'renews_at', 'canceled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_at' => 'date', 'renews_at' => 'date', 'canceled_at' => 'date'];
    }

    public function school() { return $this->belongsTo(School::class); }
}
