<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolStaff extends Model
{
    use HasFactory;

    protected $fillable = [
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
