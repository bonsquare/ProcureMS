<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->date('dv_date')->nullable()->after('dv_number');
            $table->string('dv_particulars')->nullable()->after('dv_date');
            $table->unique('dv_number');
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropUnique(['dv_number']);
            $table->dropColumn(['dv_date', 'dv_particulars']);
        });
    }
};
