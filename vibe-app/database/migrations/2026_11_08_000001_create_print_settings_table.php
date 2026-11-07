<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The margins (mm) a school prints with: doc_key null is the school's default, any other key is one document.
        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('doc_key', 40)->nullable();
            $table->decimal('margin_top', 5, 1);
            $table->decimal('margin_right', 5, 1);
            $table->decimal('margin_bottom', 5, 1);
            $table->decimal('margin_left', 5, 1);
            $table->timestamps();
            $table->unique(['school_id', 'doc_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_settings');
    }
};
