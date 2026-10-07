<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role'];

    /** Budget roles: Super Admin = master_user; school_admin keeps full access to its school. */
    public const ROLES = [
        'school_admin' => 'School Administrator',
        'school_head' => 'School Head',
        'administrative_officer' => 'Administrative Officer',
        'budget_officer' => 'Budget Officer',
        'accounting_officer' => 'Accounting Officer',
        'procurement_officer' => 'Procurement Officer',
        'bac_user' => 'BAC User',
        'liquidation_officer' => 'Liquidation Officer',
        'cashier' => 'Cashier',
        'inspector' => 'Inspector',
        'auditor' => 'Auditor',
        'viewer' => 'Viewer',
        'office_user' => 'Office User / End User',
        'encoder' => 'Encoder',
        'approver' => 'Approver',
    ];

    public function canManageBudget(): bool
    {
        return $this->hasPermission('budget.manage');
    }

    /** Auditors only view and print. */
    public function isReadOnly(): bool
    {
        return $this->role === 'auditor';
    }

    public function canCreateProcurement(): bool
    {
        return $this->hasPermission('procurement.create') || $this->hasPermission('procurement.edit');
    }

    /** ORS entries are an accounting/liquidation task; end users only submit requests. */
    public function canCreateObligation(): bool
    {
        return $this->hasPermission('liquidation.create');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'master_user') {
            return true;
        }

        foreach (config('permissions.roles.'.$this->role, []) as $granted) {
            if ($granted === '*' || $granted === $permission) {
                return true;
            }

            if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    public function activeSubscription(): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)
            ->latest('starts_at')
            ->latest('id')
            ->first();
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
