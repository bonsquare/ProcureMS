<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class BudgetAllocation extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'created_by', 'fiscal_year', 'source_of_fund', 'uacs_code', 'particulars', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function school() { return $this->belongsTo(School::class); }
}
