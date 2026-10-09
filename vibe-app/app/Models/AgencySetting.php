<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AgencySetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'agency_name', 'republic_name', 'department_name', 'region_name', 'division_office', 'district_name', 'division_address', 'division_name', 'office_section',
        'address', 'email', 'phone', 'head_name', 'department_logo_path', 'division_logo_path', 'district_address', 'district_head', 'district_logo_path', 'district_email', 'district_phone', 'division_email', 'division_phone',
        'google_drive_enabled', 'google_drive_folder_name', 'google_drive_folder_id', 'google_drive_folder_url', 'google_drive_connected_at',
    ];

    protected $casts = [
        'google_drive_enabled' => 'boolean',
        'google_drive_connected_at' => 'datetime',
    ];
}
