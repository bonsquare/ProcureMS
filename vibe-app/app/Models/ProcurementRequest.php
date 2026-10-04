<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class ProcurementRequest extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'requested_by', 'request_number', 'title', 'description', 'transaction_description', 'amount', 'entity_name', 'department_name', 'section', 'sai_number', 'sai_date', 'responsibility_center_code', 'source_of_fund', 'extra_blank_rows', 'status', 'requested_at', 'approved_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'sai_date' => 'date', 'requested_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function school() { return $this->belongsTo(School::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function liquidationReports() { return $this->hasMany(LiquidationReport::class); }
    public function items() { return $this->hasMany(ProcurementRequestItem::class); }
    public function documents() { return $this->hasMany(ProcurementDocument::class); }
}
