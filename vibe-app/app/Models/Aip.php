<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Aip extends Model
{
    use BelongsToOrganization;

    public const PILLARS = ['Access', 'Equity', 'Quality', 'Resiliency', 'Well-Being', 'Enabling Mechanism'];

    /** Source of fund choices depend on which entity the plan belongs to. */
    public const FUNDS = [
        'School' => ['MOOE', 'SEF', 'IGP', 'Others'],
        'Division Office Proper' => ['MOOE-GASS', 'MOOE-HRTD', 'MOOE-Sub-ARO', 'School MOOE', 'SEF', 'Others'],
    ];

    protected $fillable = [
        'organization_id', 'school_id', 'master_transaction_id', 'sip_project_id', 'created_by', 'fiscal_year', 'entity', 'status',
        'prepared_by_name', 'prepared_by_position', 'noted_by_name', 'noted_by_position', 'approved_by_name', 'approved_by_position', 'approved_at',
        'sip_start_year', 'sip_year_no',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function sipProject()
    {
        return $this->belongsTo(SipProject::class);
    }

    public function kras()
    {
        return $this->hasMany(AipKra::class)->orderBy('id');
    }

    public function activities()
    {
        return $this->hasMany(AipActivity::class)->orderBy('id');
    }

    public function allotments()
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function fundOptions(): array
    {
        return self::FUNDS[$this->entity] ?? self::FUNDS['School'];
    }

    /** Financial target per source of fund and quarter, as in the AIP summary table. */
    public function fundSummary()
    {
        return $this->activities->groupBy(fn ($a) => $a->source_of_fund ?: 'Unassigned')->map(fn ($group) => [
            'q1' => $group->sum('q1_amount'), 'q2' => $group->sum('q2_amount'), 'q3' => $group->sum('q3_amount'), 'q4' => $group->sum('q4_amount'),
            'total' => $group->sum(fn ($a) => $a->total),
        ]);
    }
}
