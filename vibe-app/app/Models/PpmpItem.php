<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PpmpItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'ppmp_plan_id', 'aip_activity_id', 'chart_of_account_id', 'procurement_item', 'specifications', 'quantity', 'unit', 'estimated_unit_cost', 'estimated_total_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'estimated_unit_cost' => 'decimal:2', 'estimated_total_cost' => 'decimal:2'];
    }

    public function plan()
    {
        return $this->belongsTo(PpmpPlan::class, 'ppmp_plan_id');
    }

    public function aipActivity()
    {
        return $this->belongsTo(AipActivity::class);
    }
}
