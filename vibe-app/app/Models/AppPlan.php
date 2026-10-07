<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AppPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'fiscal_year', 'status', 'created_by'];

    public function items()
    {
        return $this->hasMany(AppItem::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
