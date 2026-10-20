<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One budget line (UACS code) an ORS is charged to, with the part of the ORS amount it carries. */
class LiquidationReportLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'liquidation_report_id', 'budget_allocation_id', 'line_no', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }

    public function budgetAllocation()
    {
        return $this->belongsTo(BudgetAllocation::class);
    }
}
