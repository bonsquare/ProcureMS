<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'school_id', 'business_name', 'addressee', 'has_company_owner', 'owner_salutation', 'owner_given_name', 'owner_middle_initial',
        'owner_last_name', 'business_address', 'contact_person',
        'phone', 'email', 'tin', 'tax_type', 'tax_rate', 'business_permit_no', 'philgeps_no', 'notes', 'status',
        'business_type', 'line_of_business', 'contact_position', 'alt_phone', 'website',
        'address_street', 'address_barangay', 'address_city', 'address_province', 'address_zip',
        'dti_registration_no', 'sec_registration_no', 'cda_registration_no', 'bir_registration_no',
        'payment_method', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number',
        'business_permit_expiry', 'philgeps_expiry',
    ];

    protected $casts = ['business_permit_expiry' => 'date', 'philgeps_expiry' => 'date'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
