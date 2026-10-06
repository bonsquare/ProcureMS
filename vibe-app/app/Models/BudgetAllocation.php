<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class BudgetAllocation extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'created_by', 'budget_ref_no', 'fiscal_year', 'start_date', 'end_date',
        'source_of_fund', 'fund_name', 'program', 'chart_of_account_id', 'uacs_code', 'particulars', 'description',
        'amount', 'q1_amount', 'q2_amount', 'q3_amount', 'q4_amount', 'remarks', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'q1_amount' => 'decimal:2', 'q2_amount' => 'decimal:2', 'q3_amount' => 'decimal:2', 'q4_amount' => 'decimal:2',
            'start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime',
        ];
    }

    public function school() { return $this->belongsTo(School::class); }

    public function account() { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }

    public function procurementRequests() { return $this->hasMany(ProcurementRequest::class); }

    public function liquidationReports() { return $this->hasMany(LiquidationReport::class); }

    public function quarterAmount(int $quarter): float
    {
        return (float) $this->{"q{$quarter}_amount"};
    }
}
