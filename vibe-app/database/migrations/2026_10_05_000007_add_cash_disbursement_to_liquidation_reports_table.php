<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->string('accounting_remarks')->nullable()->after('notes');
            $table->string('dv_number')->nullable()->after('accounting_remarks');
            $table->string('payment_mode')->nullable()->after('dv_number');
            $table->string('payment_reference')->nullable()->after('payment_mode');
            $table->date('paid_at')->nullable()->after('payment_reference');
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['accounting_remarks', 'dv_number', 'payment_mode', 'payment_reference', 'paid_at']);
        });
    }
};
