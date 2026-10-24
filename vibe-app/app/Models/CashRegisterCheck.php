<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** A check number typed in the Cash Disbursement Register form: the register prints only these checks, in check-number order. */
class CashRegisterCheck extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'source_of_fund', 'quarter_start', 'check_number', 'created_by'];

    protected function casts(): array
    {
        return ['quarter_start' => 'date'];
    }
}
