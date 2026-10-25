<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The two withholding rates that make up the supplier's total tax rate (tax_rate): 1600VT (VAT) or 1600PT (Non-VAT), and 0619E. Old suppliers keep only the total.
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('tax_rate_1600', 5, 2)->nullable()->after('tax_rate');
            $table->decimal('tax_rate_0619e', 5, 2)->nullable()->after('tax_rate_1600');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['tax_rate_1600', 'tax_rate_0619e']);
        });
    }
};
