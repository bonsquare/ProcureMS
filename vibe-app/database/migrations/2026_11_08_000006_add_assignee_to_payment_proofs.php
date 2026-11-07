<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_telegram_id')->nullable();
            $table->string('assigned_name', 80)->nullable();
            $table->timestamp('assigned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn(['assigned_telegram_id', 'assigned_name', 'assigned_at']);
        });
    }
};
