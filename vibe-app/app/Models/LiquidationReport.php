<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LiquidationReport extends Model
{
    use BelongsToOrganization, HasFactory;

    /** The "Less:" deductions a DV can carry, by key. */
    public const DV_DEDUCTIONS = ['withholding_tax' => 'Withholding Tax', 'liquidated_damages' => 'Liquidated Damages', 'retention' => 'Retention', 'mobilization' => 'Mobilization'];

    protected $fillable = ['ors_kind', 'dv_deductions', 'dv_include_appropriation', 'ors_include_status', 'payee_office', 'ors_show_uacs', 'organization_id', 'school_id', 'master_transaction_id', 'procurement_request_id', 'submitted_by', 'report_number', 'ors_number', 'source_of_fund', 'payee', 'payee_address', 'payee_tin', 'responsibility_center_code', 'purpose', 'amount', 'status', 'notes', 'accounting_remarks', 'dv_number', 'dv_date', 'dv_particulars', 'payment_mode', 'payment_reference', 'paid_at', 'check_date', 'bank_name', 'bank_account_name', 'bank_account_number', 'payment_fund_cluster', 'received_by', 'payment_remarks', 'paid_by', 'submitted_at', 'approved_at', 'budget_allocation_id', 'signatories', 'reissue_pending', 'dv_fund_cluster', 'ors_fund_cluster'];

    protected function casts(): array
    {
        return ['dv_deductions' => 'array', 'amount' => 'decimal:2', 'paid_at' => 'date', 'check_date' => 'date', 'dv_date' => 'date', 'dv_include_appropriation' => 'boolean', 'ors_include_status' => 'boolean', 'ors_show_uacs' => 'boolean', 'reissue_pending' => 'boolean', 'signatories' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function documentChecks()
    {
        return $this->hasMany(LiquidationDocumentCheck::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function procurementRequest()
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function budgetAllocation()
    {
        return $this->belongsTo(BudgetAllocation::class);
    }

    /**
     * The withholding tax the DV suggests from the payee's tax type and tax rate in the Supplier Manager:
     * VAT = amount / 1.12 x the rate; Non-VAT = amount x the rate; none for VAT exempt or a payee that is not a supplier.
     */
    public function suggestedWithholdingTax(): float
    {
        $payee = $this->payee ?: $this->procurementRequest?->supplier_name;
        $supplier = $payee ? Supplier::where('business_name', $payee)->first() : null;
        $amount = (float) $this->amount;
        $rate = (float) $supplier?->tax_rate / 100;

        return round(match ($supplier?->tax_type) {
            'vat' => $amount / 1.12 * $rate,
            'non_vat' => $amount * $rate,
            default => 0,
        }, 2);
    }

    /** The budget line this obligation is charged to: its own, or the one on the linked PR. */
    public function chargedLine(): ?BudgetAllocation
    {
        return $this->budgetAllocation ?? $this->procurementRequest?->budgetAllocation;
    }

    /** The budget lines (UACS codes) of an ORS that is charged to more than one, each with its amount; none for an ORS with one line. */
    public function lines()
    {
        return $this->hasMany(LiquidationReportLine::class)->orderBy('line_no');
    }

    /**
     * Every budget line this obligation is charged to with the amount on each: the split lines when there are any, else the one line with the whole amount.
     *
     * @return Collection<int, array{allocation: BudgetAllocation, amount: float}>
     */
    public function chargedLines(): Collection
    {
        $split = $this->lines()->with('budgetAllocation.account')->get();
        if ($split->isNotEmpty()) {
            return $split->map(fn (LiquidationReportLine $line) => ['allocation' => $line->budgetAllocation, 'amount' => (float) $line->amount])->values();
        }

        $line = $this->chargedLine();

        return $line ? collect([['allocation' => $line, 'amount' => (float) $this->amount]]) : collect();
    }

    /** The double-entry journal of the DV, in print order. */
    public function journalLines()
    {
        return $this->hasMany(DvJournalLine::class)->orderBy('line_no');
    }

    /** The breakdown of the advance by actual expense account (optional); the cash reports read it. */
    public function breakdownLines()
    {
        return $this->hasMany(DvBreakdownLine::class)->orderBy('line_no');
    }

    public function cancelledChecks()
    {
        return $this->hasMany(CancelledCheck::class);
    }

    /** A DV number as people read it: it usually starts with "DV-" already, so the letters are not put in front twice. */
    public static function dvLabel(?string $number): string
    {
        $number = (string) $number;

        return preg_match('/^DV\b/i', $number) ? $number : trim('DV '.$number);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** The records that have an ORS; a manual DV (no ORS number) is a payment only and never an obligation. */
    public function scopeWithOrs($query)
    {
        return $query->whereNotNull('ors_number');
    }

    /** Next system-generated ORS serial number for the current year, e.g. ORS-2026-0007. */
    public static function nextOrsNumber(?int $organizationId = null): string
    {
        $organizationId ??= (int) auth()->user()?->organization_id;

        return app(DocumentNumberService::class)
            ->preview($organizationId, 'obligation_request', 'ORS');
    }
}
