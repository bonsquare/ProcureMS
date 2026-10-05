<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->string('payee_address')->nullable()->after('payee');
            $table->string('payee_tin')->nullable()->after('payee_address');
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn(['payee_address', 'payee_tin']);
        });
    }
};
