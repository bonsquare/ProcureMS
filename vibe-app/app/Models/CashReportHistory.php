<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** A cash report as it was when it was generated (opened to print): the finished page is kept, so it can be opened again unchanged. */
class CashReportHistory extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'report', 'period_from', 'period_to', 'period_text', 'source_of_fund', 'report_no', 'total_payments', 'html', 'generated_by'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'total_payments' => 'decimal:2'];
    }

    /** The name of the kept report. */
    public function label(): string
    {
        return ['roci' => 'Report of Checks Issued', 'checks' => 'Checks and Advices to Debit Account', 'register' => 'Cash Disbursement Register', 'bank' => 'Bank Reconciliation'][$this->report] ?? (string) $this->report;
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
