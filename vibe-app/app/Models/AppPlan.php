<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AppPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'fiscal_year', 'status', 'created_by',
        'prepared_by_role', 'prepared_by_name', 'prepared_by_position', 'recommended_by_role', 'recommended_by_name', 'recommended_by_position',
        'approved_by_role', 'approved_by_name', 'approved_by_position',
    ];

    public function items()
    {
        return $this->hasMany(AppItem::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
