<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // The APPROPRIATION table at the foot of the printed DV is optional; it is off unless the user asks for it.
            $table->boolean('dv_include_appropriation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('dv_include_appropriation');
        });
    }
};
