<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The refund of an unspent advance is recorded as cash received when its liquidation is approved, and removed if the liquidation is reopened.
        Schema::table('cash_fund_receipts', function (Blueprint $table) {
            $table->foreignId('check_liquidation_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_fund_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_liquidation_id');
        });
    }
};
