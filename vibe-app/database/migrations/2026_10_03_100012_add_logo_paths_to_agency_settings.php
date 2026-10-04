<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->string('department_logo_path')->nullable()->after('head_name');
            $table->string('division_logo_path')->nullable()->after('department_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->dropColumn(['department_logo_path', 'division_logo_path']);
        });
    }
};
