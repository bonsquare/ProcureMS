<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** The actual expenses of one check issued as an advance. Once approved it is the liquidation adjustment: Dr each expense account, Cr Advances to Operating Expenses. */
class CheckLiquidation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'liquidation_report_id', 'status', 'check_amount', 'refund_amount', 'refund_reference', 'version', 'prepared_by', 'approved_by', 'approved_at', 'reopened_by', 'reopen_reason'];

    protected function casts(): array
    {
        return ['check_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }

    public function lines()
    {
        return $this->hasMany(CheckLiquidationLine::class)->orderBy('line_no');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
