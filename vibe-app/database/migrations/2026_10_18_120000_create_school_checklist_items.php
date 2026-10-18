<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('ors_kind', 20); // goods | direct | travel | other
            $table->string('stage', 10);    // ors | dv
            $table->string('label', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_checklist_items');
    }
};
