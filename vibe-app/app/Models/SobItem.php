<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One line of an SOB: what is bought for an AIP activity (the PPA), how often, how many and at what cost. */
class SobItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'sob_plan_id', 'aip_activity_id', 'chart_of_account_id', 'particulars', 'frequency', 'quantity', 'unit', 'unit_cost', 'amount'];

    protected function casts(): array
    {
        return ['frequency' => 'decimal:2', 'quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function plan()
    {
        return $this->belongsTo(SobPlan::class, 'sob_plan_id');
    }

    public function activity()
    {
        return $this->belongsTo(AipActivity::class, 'aip_activity_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function appItems()
    {
        return $this->hasMany(AppItem::class, 'sob_item_id');
    }
}
