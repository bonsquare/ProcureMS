<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('source_of_fund');
            $table->string('uacs_code')->nullable();
            $table->string('particulars')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
    }
};
