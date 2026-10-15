<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Whether the UACS object code of the charged budget line is written in the UACS Object Code column of the printed ORS. */
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->boolean('ors_show_uacs')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('ors_show_uacs');
        });
    }
};
