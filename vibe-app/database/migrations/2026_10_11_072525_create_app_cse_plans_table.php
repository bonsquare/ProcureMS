<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The APP-CSE of one school for one fiscal year: the header of the official form, its budget line, its signers and its status. */
    public function up(): void
    {
        Schema::create('app_cse_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedSmallInteger('catalog_year');
            $table->string('status', 20)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('office_name')->nullable();
            $table->string('region')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('account_code')->nullable();
            $table->string('organization_type')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->decimal('approved_budget', 15, 2)->nullable();
            $table->text('approved_budget_words')->nullable();
            $table->date('date_prepared')->nullable();
            $table->string('agency_head_name')->nullable();
            $table->string('agency_head_position')->nullable();
            $table->json('signatories')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cse_plans');
    }
};
