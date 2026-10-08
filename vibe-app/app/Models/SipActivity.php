<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SipActivity extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'sip_project_id', 'activity',
        'physical_year1', 'physical_year2', 'physical_year3',
        'financial_year1', 'financial_year2', 'financial_year3',
        'source_of_fund', 'responsible_person', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'physical_year1' => 'decimal:2', 'physical_year2' => 'decimal:2', 'physical_year3' => 'decimal:2',
            'financial_year1' => 'decimal:2', 'financial_year2' => 'decimal:2', 'financial_year3' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(SipProject::class, 'sip_project_id');
    }

    public function getFinancialTotalAttribute(): float
    {
        return (float) $this->financial_year1 + (float) $this->financial_year2 + (float) $this->financial_year3;
    }
}
