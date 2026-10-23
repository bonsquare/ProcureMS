<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One line of the breakdown of an advance: the actual expense account and the part of the DV amount it covers. The cash reports read it. */
class DvBreakdownLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'liquidation_report_id', 'line_no', 'account_code', 'account_title', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }
}
