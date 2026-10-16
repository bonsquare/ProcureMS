<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CancelledCheck extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'liquidation_report_id', 'ors_number', 'dv_number', 'check_number', 'payment_mode', 'paid_at', 'amount', 'reason', 'cancelled_by', 'cancelled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function liquidationReport()
    {
        return $this->belongsTo(LiquidationReport::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
