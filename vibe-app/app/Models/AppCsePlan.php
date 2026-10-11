<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** The APP-CSE (Annual Procurement Plan for Common Supplies and Equipment) of one school for one fiscal year. Every rule lives in AppCseService. */
class AppCsePlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'fiscal_year', 'catalog_year', 'status', 'approved_at', 'created_by',
        'office_name', 'region', 'address', 'account_code', 'organization_type', 'contact_person', 'contact_position', 'contact_email', 'contact_phone',
        'approved_budget', 'approved_budget_words', 'date_prepared', 'agency_head_name', 'agency_head_position', 'signatories',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer', 'catalog_year' => 'integer', 'approved_at' => 'datetime', 'date_prepared' => 'date',
            'approved_budget' => 'decimal:2', 'signatories' => 'array',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function items()
    {
        return $this->hasMany(AppCseItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
