<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class StaffRoleOption extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'role_group', 'name'];
}
