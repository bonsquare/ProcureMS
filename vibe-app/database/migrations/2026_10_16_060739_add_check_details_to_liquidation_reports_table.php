<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->date('check_date')->nullable()->after('paid_at');
            $table->string('bank_name')->nullable()->after('check_date');
            $table->string('received_by')->nullable()->after('bank_name');
            $table->string('payment_remarks')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn(['check_date', 'bank_name', 'received_by', 'payment_remarks']);
        });
    }
};
