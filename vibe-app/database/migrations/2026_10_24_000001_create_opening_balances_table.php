<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('fund_cluster', 100)->default('General Fund');
            $table->date('as_of');
            $table->string('account_code', 50);
            $table->string('account_title');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'fund_cluster', 'account_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};
