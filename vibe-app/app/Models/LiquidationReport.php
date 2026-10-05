<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class LiquidationReport extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'procurement_request_id', 'submitted_by', 'report_number', 'ors_number', 'source_of_fund', 'payee', 'payee_address', 'payee_tin', 'responsibility_center_code', 'purpose', 'amount', 'status', 'notes', 'accounting_remarks', 'dv_number', 'dv_date', 'dv_particulars', 'payment_mode', 'payment_reference', 'paid_at', 'paid_by', 'submitted_at', 'approved_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'dv_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function school() { return $this->belongsTo(School::class); }
    public function procurementRequest() { return $this->belongsTo(ProcurementRequest::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
}
