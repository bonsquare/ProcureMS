<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToOrganization;

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'procurement_role', 'bac_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToOrganization;

    protected $fillable = ['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'procurement_role', 'bac_role'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function procurementRequests()
    {
        return $this->hasMany(ProcurementRequest::class, 'requested_by');
    }

    public function liquidationReports()
    {
        return $this->hasMany(LiquidationReport::class, 'submitted_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
