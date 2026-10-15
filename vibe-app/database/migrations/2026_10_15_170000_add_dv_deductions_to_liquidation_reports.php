<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // The "Less:" deductions of a DV (withholding tax, liquidated damages, retention, mobilization), keyed by name.
            $table->json('dv_deductions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('dv_deductions');
        });
    }
};
