<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Signatories of the printed School Improvement Plan for one school and three-year period. */
class SipPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'start_year',
        'prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
