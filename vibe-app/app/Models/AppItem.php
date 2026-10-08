<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AppItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'app_plan_id', 'ppmp_item_id', 'procurement_item', 'specifications', 'quantity', 'unit', 'estimated_unit_cost', 'estimated_total_cost', 'procurement_mode', 'procurement_schedule', 'fund_source', 'status'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'estimated_unit_cost' => 'decimal:2', 'estimated_total_cost' => 'decimal:2'];
    }

    public function plan()
    {
        return $this->belongsTo(AppPlan::class, 'app_plan_id');
    }

    public function ppmpItem()
    {
        return $this->belongsTo(PpmpItem::class);
    }

    public function requestItems()
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }
}
