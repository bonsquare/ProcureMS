<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** One paper of a liquidation checklist that was ticked by hand: received, or not required for that transaction. A paper with no row is missing. */
class LiquidationDocumentCheck extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'liquidation_report_id', 'item_key', 'state', 'note', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function report()
    {
        return $this->belongsTo(LiquidationReport::class, 'liquidation_report_id');
    }
}
