<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One material line of a Program of Works. */
class PowItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'pow_plan_id', 'sob_item_id', 'description', 'unit', 'quantity', 'unit_cost', 'amount', 'sort'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function plan()
    {
        return $this->belongsTo(PowPlan::class, 'pow_plan_id');
    }
}
