<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('source_of_fund', 50)->default('');
            $table->date('quarter_start');
            $table->string('check_number', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'source_of_fund', 'quarter_start', 'check_number'], 'cash_register_checks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_checks');
    }
};
