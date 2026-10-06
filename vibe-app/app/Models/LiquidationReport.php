<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class LiquidationReport extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'procurement_request_id', 'submitted_by', 'report_number', 'ors_number', 'source_of_fund', 'payee', 'payee_address', 'payee_tin', 'responsibility_center_code', 'purpose', 'amount', 'status', 'notes', 'accounting_remarks', 'dv_number', 'dv_date', 'dv_particulars', 'payment_mode', 'payment_reference', 'paid_at', 'paid_by', 'submitted_at', 'approved_at', 'budget_allocation_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'dv_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function school() { return $this->belongsTo(School::class); }
    public function procurementRequest() { return $this->belongsTo(ProcurementRequest::class); }

    public function budgetAllocation() { return $this->belongsTo(BudgetAllocation::class); }

    /** The budget line this obligation is charged to: its own, or the one on the linked PR. */
    public function chargedLine(): ?BudgetAllocation
    {
        return $this->budgetAllocation ?? $this->procurementRequest?->budgetAllocation;
    }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }

    /** Next system-generated ORS serial number for the current year, e.g. ORS-2026-0007. */
    public static function nextOrsNumber(): string
    {
        $prefix = 'ORS-' . now()->format('Y') . '-';
        $last = static::withoutGlobalScopes()->where('ors_number', 'like', $prefix . '%')->pluck('ors_number')
            ->map(fn ($number) => (int) substr($number, strlen($prefix)))->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
