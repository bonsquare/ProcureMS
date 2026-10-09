<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $strings = [
        'business_type', 'line_of_business', 'contact_position', 'alt_phone', 'website',
        'address_street', 'address_barangay', 'address_city', 'address_province', 'address_zip',
        'dti_registration_no', 'sec_registration_no', 'cda_registration_no', 'bir_registration_no',
        'payment_method', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number',
    ];

    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach ($this->strings as $column) {
                $table->string($column)->nullable();
            }
            $table->date('business_permit_expiry')->nullable();
            $table->date('philgeps_expiry')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([...$this->strings, 'business_permit_expiry', 'philgeps_expiry']);
        });
    }
};
