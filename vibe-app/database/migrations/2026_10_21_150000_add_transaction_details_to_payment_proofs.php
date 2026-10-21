<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            // What the master needs to match the payment: who sent it (kept as it was), the account paid from, the exact time, and the nature of the payment (not shown to the school).
            $table->string('bank_account_used', 120)->nullable()->after('reference');
            $table->dateTime('transacted_at')->nullable()->after('paid_at');
            $table->string('remarks', 255)->nullable()->after('transacted_at');
            $table->string('submitted_by_name', 190)->nullable()->after('user_id');
            $table->string('submitted_by_username', 100)->nullable()->after('submitted_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn(['bank_account_used', 'transacted_at', 'remarks', 'submitted_by_name', 'submitted_by_username']);
        });
    }
};
