<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One line of the breakdown of a journal line (for example the advance): an actual account with a debit or a credit. The cash reports read it. */
class DvBreakdownLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'liquidation_report_id', 'journal_line_no', 'line_no', 'account_code', 'account_title', 'debit', 'credit'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }
}
