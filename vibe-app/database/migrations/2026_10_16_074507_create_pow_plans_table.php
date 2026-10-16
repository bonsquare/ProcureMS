<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A saved Program of Works: a snapshot made from the SOB items of one program and activity. */
    public function up(): void
    {
        Schema::create('pow_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sob_plan_id')->constrained('sob_plans')->restrictOnDelete();
            $table->unsignedBigInteger('aip_activity_id')->nullable();
            $table->string('program');
            $table->string('project_title');
            $table->string('school_address')->nullable();
            $table->date('pow_date')->nullable();
            $table->date('date_started')->nullable();
            $table->date('date_completed')->nullable();
            $table->text('manpower')->nullable();
            $table->text('equipment')->nullable();
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->json('signatories')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pow_plans');
    }
};
