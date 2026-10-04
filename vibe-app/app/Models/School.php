<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class School extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'code', 'name', 'address', 'region', 'division', 'district', 'school_type', 'school_head', 'contact_email', 'contact_number', 'status', 'logo_path'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function staff()
    {
        return $this->hasMany(SchoolStaff::class);
    }

    public function procurementRequests()
    {
        return $this->hasMany(ProcurementRequest::class);
    }

    public function liquidationReports()
    {
        return $this->hasMany(LiquidationReport::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
