<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->string('source_of_fund')->nullable()->after('ors_number');
            $table->string('payee')->nullable()->after('source_of_fund');
            $table->string('responsibility_center_code')->nullable()->after('payee');
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn(['source_of_fund', 'payee', 'responsibility_center_code']);
        });
    }
};
