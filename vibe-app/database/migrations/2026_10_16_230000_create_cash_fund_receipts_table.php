<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_fund_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->date('received_at');
            $table->string('kind', 30);
            $table->string('fund_cluster', 100)->default('General Fund');
            $table->string('bank_account_number', 60)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['school_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_fund_receipts');
    }
};
