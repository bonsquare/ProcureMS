<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Money a school received for its cash reports: the opening balance, an NCA or a cash advance. */
class CashFundReceipt extends Model
{
    use BelongsToOrganization;

    public const KINDS = ['opening_balance' => 'Opening balance', 'balance_forwarded' => 'Balance forwarded', 'nca' => 'NCA received', 'cash_advance' => 'Cash advance'];

    /** The specific funds (source of fund) offered for the downloaded cash; Others lets the user type the fund's name. */
    public const FUNDS = ['MOOE', 'ARAL', 'SBFP', 'SEF', 'IGP', 'Others'];

    protected $fillable = ['organization_id', 'school_id', 'received_at', 'kind', 'source_of_fund', 'fund_cluster', 'bank_account_number', 'amount', 'reference', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
