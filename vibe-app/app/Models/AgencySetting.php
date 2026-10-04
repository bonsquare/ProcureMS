<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class AgencySetting extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'organization_id', 'agency_name', 'republic_name', 'department_name', 'region_name', 'division_office', 'district_name', 'division_name', 'office_section',
        'address', 'email', 'phone', 'head_name', 'department_logo_path', 'division_logo_path',
    ];
}
