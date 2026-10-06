<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AipActivity extends Model
{
    protected $fillable = [
        'aip_id', 'aip_kra_id', 'activity', 'physical_target', 'timeline', 'q1_amount', 'q2_amount', 'q3_amount', 'q4_amount',
        'source_of_fund', 'chart_of_account_id', 'responsible_persons', 'remarks_list',
    ];

    protected function casts(): array
    {
        return [
            'q1_amount' => 'decimal:2', 'q2_amount' => 'decimal:2', 'q3_amount' => 'decimal:2', 'q4_amount' => 'decimal:2',
            'responsible_persons' => 'array', 'remarks_list' => 'array',
        ];
    }

    public function aip() { return $this->belongsTo(Aip::class); }

    public function kra() { return $this->belongsTo(AipKra::class, 'aip_kra_id'); }

    public function account() { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }

    public function getTotalAttribute(): float
    {
        return (float) $this->q1_amount + (float) $this->q2_amount + (float) $this->q3_amount + (float) $this->q4_amount;
    }
}
