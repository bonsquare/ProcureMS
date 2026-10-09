<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One line of the double-entry journal of a disbursement voucher: an account with a debit or a credit. */
class DvJournalLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'liquidation_report_id', 'line_no', 'account_code', 'account_title', 'debit', 'credit'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }
}
