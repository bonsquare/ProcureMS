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

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role', 'username', 'user_code', 'phone', 'status', 'last_login_at', 'password_changed_at', 'deactivated_at', 'deactivation_reason', 'deactivation_note'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'organization_id', 'school_id', 'position', 'office', 'procurement_role', 'bac_role', 'username', 'user_code', 'phone', 'status', 'last_login_at', 'password_changed_at', 'deactivated_at', 'deactivation_reason', 'deactivation_note'];

    protected static function booted(): void
    {
        // Every account gets a readable User ID (and a username to sign in with) the moment it is created.
        static::created(function (self $user) {
            $updates = [];
            if (! $user->user_code) {
                $updates['user_code'] = 'USR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);
            }
            if (! $user->username) {
                $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr((string) $user->email, '@', true) ?: 'user'.$user->id)) ?: 'user'.$user->id;
                $updates['username'] = static::withoutGlobalScopes()->where('username', $base)->exists() ? $base.$user->id : $base;
            }
            if ($updates) {
                static::withoutGlobalScopes()->whereKey($user->id)->update($updates);
                $user->forceFill($updates)->syncOriginal();
            }
        });
    }

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
        $latest = fn ($query) => $query->latest('starts_at')->latest('id');

        // The subscription is personal and follows the user; unowned (legacy) rows still cover the school's other users.
        return $latest(Subscription::withoutGlobalScopes()->where('user_id', $this->id))->first()
            ?? $latest(Subscription::withoutGlobalScopes()->where('organization_id', $this->organization_id))->first();
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
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }
}
