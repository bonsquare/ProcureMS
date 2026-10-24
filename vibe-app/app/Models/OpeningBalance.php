<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** The balance of one account when a school's books start; the financial reports begin from it. */
class OpeningBalance extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'fund_cluster', 'as_of', 'account_code', 'account_title', 'debit', 'credit'];

    protected function casts(): array
    {
        return ['as_of' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
