<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SipProject extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'master_transaction_id', 'school_year', 'planning_period', 'pillar', 'kra', 'organizational_outcome', 'strategy', 'five_point_agenda', 'goal', 'objective', 'project', 'activity', 'expected_output', 'target', 'performance_indicator', 'implementation_schedule', 'responsible_person', 'estimated_budget', 'fund_source', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['estimated_budget' => 'decimal:2', 'school_year' => 'integer'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function activities()
    {
        return $this->hasMany(SipActivity::class)->orderBy('id');
    }

    public function aips()
    {
        return $this->hasMany(Aip::class, 'sip_project_id');
    }
}
