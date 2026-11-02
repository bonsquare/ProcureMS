<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // A non-cash DV (for example receiving advances in Cash-MDS) is recorded in its journal entry only: no payment, no cash report.
            $table->boolean('dv_non_cash')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('dv_non_cash');
        });
    }
};
