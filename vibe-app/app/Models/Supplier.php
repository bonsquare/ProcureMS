<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class Supplier extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'organization_id', 'business_name', 'addressee', 'has_company_owner', 'owner_salutation', 'owner_given_name', 'owner_middle_initial',
        'owner_last_name', 'business_address', 'contact_person',
        'phone', 'email', 'tin', 'tax_type', 'tax_rate', 'business_permit_no', 'philgeps_no', 'notes', 'status',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
