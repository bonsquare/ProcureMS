<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The contact number and e-mail of an employee (the BAC Secretariat's are printed on the Request for Quotation). */
    public function up(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->string('contact_number', 50)->nullable()->after('position');
            $table->string('email')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'email']);
        });
    }
};
