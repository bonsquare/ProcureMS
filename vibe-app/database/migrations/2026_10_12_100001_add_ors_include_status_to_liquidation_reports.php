<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // Section C (Status of Obligation) is not on every school's ORS template; it prints unless the user turns it off.
            $table->boolean('ors_include_status')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('ors_include_status');
        });
    }
};
