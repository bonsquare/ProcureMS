<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** A Program of Works made from the SOB items of one program and activity; it keeps its own copy of the lines. */
class PowPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'sob_plan_id', 'aip_activity_id', 'program', 'project_title', 'school_address', 'pow_date', 'date_started',
        'date_completed', 'manpower', 'equipment', 'labor_cost', 'signatories', 'created_by',
    ];

    protected function casts(): array
    {
        return ['pow_date' => 'date', 'date_started' => 'date', 'date_completed' => 'date', 'labor_cost' => 'decimal:2', 'signatories' => 'array'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function sobPlan()
    {
        return $this->belongsTo(SobPlan::class);
    }

    public function items()
    {
        return $this->hasMany(PowItem::class)->orderBy('sort')->orderBy('id');
    }
}
