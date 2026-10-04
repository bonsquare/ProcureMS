<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->string('republic_name')->nullable()->after('agency_name');
            $table->string('region_name')->nullable()->after('department_name');
            $table->string('division_office')->nullable()->after('region_name');
            $table->string('district_name')->nullable()->after('division_office');
        });
    }

    public function down(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->dropColumn(['republic_name', 'region_name', 'division_office', 'district_name']);
        });
    }
};
