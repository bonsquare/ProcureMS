<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** The School Operating Budget of one school for one quarter of a fiscal year, built from its AIP. */
class SobPlan extends Model
{
    use BelongsToOrganization;

    public const QUARTERS = [1 => '1ST QUARTER', 2 => '2ND QUARTER', 3 => '3RD QUARTER', 4 => '4TH QUARTER'];

    protected $fillable = [
        'organization_id', 'school_id', 'aip_id', 'master_transaction_id', 'fiscal_year', 'quarter', 'fund_source', 'status',
        'prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position',
        'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'fiscal_year' => 'integer', 'quarter' => 'integer'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function aip()
    {
        return $this->belongsTo(Aip::class);
    }

    public function items()
    {
        return $this->hasMany(SobItem::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function quarterLabel(): string
    {
        return self::QUARTERS[$this->quarter] ?? '';
    }

    public function total(): float
    {
        return (float) $this->items()->sum('amount');
    }
}
