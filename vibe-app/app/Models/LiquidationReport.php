<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class LiquidationReport extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'procurement_request_id', 'submitted_by', 'report_number', 'amount', 'status', 'notes', 'submitted_at', 'approved_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function school() { return $this->belongsTo(School::class); }
    public function procurementRequest() { return $this->belongsTo(ProcurementRequest::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
}
