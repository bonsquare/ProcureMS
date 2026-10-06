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

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToOrganization;

    protected $fillable = ['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role'];

    /** Budget roles: Super Admin = master_user; school_admin keeps full access to its school. */
    public const ROLES = [
        'school_admin' => 'School Administrator',
        'budget_officer' => 'Budget Officer',
        'accounting_officer' => 'Accounting Officer',
        'procurement_officer' => 'Procurement Officer',
        'liquidation_officer' => 'Liquidation Officer',
        'auditor' => 'Auditor',
        'office_user' => 'Office User / End User',
        'encoder' => 'Encoder',
        'approver' => 'Approver',
    ];

    public function canManageBudget(): bool
    {
        return in_array($this->role, ['master_user', 'school_admin', 'budget_officer'], true);
    }

    /** Auditors only view and print. */
    public function isReadOnly(): bool
    {
        return $this->role === 'auditor';
    }

    public function canCreateProcurement(): bool
    {
        return !$this->isReadOnly();
    }

    /** ORS entries are an accounting/liquidation task; end users only submit requests. */
    public function canCreateObligation(): bool
    {
        return !in_array($this->role, ['auditor', 'office_user'], true);
    }

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
