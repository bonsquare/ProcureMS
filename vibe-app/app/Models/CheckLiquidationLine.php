<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One expense account of a check liquidation with its amount; source says where the line came from (manual, ors_split, ors_main). */
class CheckLiquidationLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'check_liquidation_id', 'line_no', 'account_code', 'account_title', 'amount', 'source'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function liquidation()
    {
        return $this->belongsTo(CheckLiquidation::class, 'check_liquidation_id');
    }
}
