<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class SchoolStaff extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'school_id',
        'name',
        'position',
        'procurement_role',
        'document_role',
        'bac_role',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
