<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // Fund Cluster typed on the ORS; empty means 101101 is printed.
            $table->string('ors_fund_cluster', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('ors_fund_cluster');
        });
    }
};
