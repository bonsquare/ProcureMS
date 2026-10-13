<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One line of an APP-CSE: a Procurement Service catalog item (Part I) or the school's own item (Part II), with 12 monthly quantities. */
class AppCseItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'app_cse_plan_id', 'cse_catalog_item_id', 'category', 'description', 'unit', 'unit_price',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12', 'sort_order', 'from_sob',
    ];

    protected function casts(): array
    {
        $months = array_fill_keys(['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'], 'decimal:2');

        return ['unit_price' => 'decimal:2', 'sort_order' => 'integer', 'from_sob' => 'boolean'] + $months;
    }

    public function plan()
    {
        return $this->belongsTo(AppCsePlan::class, 'app_cse_plan_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(CseCatalogItem::class, 'cse_catalog_item_id');
    }

    /** A Part II item: the school's own, not from the Procurement Service catalog. */
    public function isCustom(): bool
    {
        return $this->cse_catalog_item_id === null;
    }

    /** @return array<int, float> the quantity of each month, January first */
    public function months(): array
    {
        return array_map(fn (int $month) => (float) $this->{'m'.$month}, range(1, 12));
    }

    public function quarterQuantity(int $quarter): float
    {
        return round(array_sum(array_slice($this->months(), ($quarter - 1) * 3, 3)), 2);
    }

    public function quarterAmount(int $quarter): float
    {
        return round($this->quarterQuantity($quarter) * (float) $this->unit_price, 2);
    }

    public function totalQuantity(): float
    {
        return round(array_sum(array_map(fn (int $quarter) => $this->quarterQuantity($quarter), range(1, 4))), 2);
    }

    /** The sum of the four quarter amounts, so the item totals, the quarter columns and lines A and G of the form always add up. */
    public function totalAmount(): float
    {
        return round(array_sum(array_map(fn (int $quarter) => $this->quarterAmount($quarter), range(1, 4))), 2);
    }
}
