<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_report_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->string('report', 30)->default('register');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('period_text', 80);
            $table->string('source_of_fund', 50)->nullable();
            $table->string('report_no', 50)->nullable();
            $table->decimal('total_payments', 14, 2)->default(0);
            $table->longText('html');
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamps();
            $table->index(['school_id', 'report', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_report_histories');
    }
};
