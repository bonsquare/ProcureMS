<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PpmpPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'aip_id', 'master_transaction_id', 'fiscal_year', 'project_title', 'procurement_mode', 'procurement_schedule', 'fund_source', 'status', 'created_by'];

    public function items()
    {
        return $this->hasMany(PpmpItem::class);
    }

    public function aip()
    {
        return $this->belongsTo(Aip::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
