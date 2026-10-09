<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationReport extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['dv_include_appropriation', 'organization_id', 'school_id', 'master_transaction_id', 'procurement_request_id', 'submitted_by', 'report_number', 'ors_number', 'source_of_fund', 'payee', 'payee_address', 'payee_tin', 'responsibility_center_code', 'purpose', 'amount', 'status', 'notes', 'accounting_remarks', 'dv_number', 'dv_date', 'dv_particulars', 'payment_mode', 'payment_reference', 'paid_at', 'paid_by', 'submitted_at', 'approved_at', 'budget_allocation_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'dv_date' => 'date', 'dv_include_appropriation' => 'boolean', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
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

    /** The budget line this obligation is charged to: its own, or the one on the linked PR. */
    public function chargedLine(): ?BudgetAllocation
    {
        return $this->budgetAllocation ?? $this->procurementRequest?->budgetAllocation;
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Next system-generated ORS serial number for the current year, e.g. ORS-2026-0007. */
    public static function nextOrsNumber(?int $organizationId = null): string
    {
        $organizationId ??= (int) auth()->user()?->organization_id;

        return app(DocumentNumberService::class)
            ->preview($organizationId, 'obligation_request', 'ORS');
    }
}
