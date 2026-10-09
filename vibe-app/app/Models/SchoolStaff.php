<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolStaff extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'school_id',
        'name',
        'position',
        'procurement_role',
        'document_role',
        'bac_role',
        'employee_no',
        'ended_at',
    ];

    /** The role groups an employee can hold, each stored as a comma separated list so one person can carry several. */
    public const ROLE_GROUPS = [
        'bac_role' => 'BAC',
        'procurement_role' => 'Procurement',
        'document_role' => 'Documents',
    ];

    /** Roles every school starts with; schools add their own on top. */
    public const DEFAULT_ROLES = [
        'bac_role' => ['BAC Chairperson', 'BAC Vice Chairperson', 'BAC Secretariat', 'BAC Member', 'TWG Member', 'BAC Observer'],
        'procurement_role' => ['Requesting Officer', 'Procurement Officer', 'Approver', 'Canvasser', 'Supply Officer'],
        'document_role' => ['Disbursing Officer', 'Inspection Officer', 'Property Custodian', 'Accountant', 'Budget Officer', 'Cashier'],
    ];

    protected static function booted(): void
    {
        // Employees who left the school's station stay in the table as history but drop out of every list and role lookup.
        static::addGlobalScope('active', fn ($query) => $query->whereNull($query->getModel()->getTable().'.ended_at'));

        static::created(function (self $staff) {
            if (! $staff->employee_no) {
                $number = 'EMP-'.str_pad((string) $staff->id, 6, '0', STR_PAD_LEFT);
                static::withoutGlobalScopes()->whereKey($staff->id)->update(['employee_no' => $number]);
                $staff->forceFill(['employee_no' => $number])->syncOriginal();
            }
        });
    }

    /** @return array<int, string> */
    public function rolesFor(string $group): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->{$group}))));
    }

    public function hasRole(string $group, string $role): bool
    {
        return in_array(mb_strtolower($role), array_map('mb_strtolower', $this->rolesFor($group)), true);
    }

    /** @param array<int, string> $roles */
    public function setRoles(string $group, array $roles): void
    {
        $clean = collect($roles)->map(fn ($role) => trim(str_replace(',', ' ', (string) $role)))->filter()->unique()->values();
        $this->{$group} = $clean->isEmpty() ? null : $clean->implode(', ');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
