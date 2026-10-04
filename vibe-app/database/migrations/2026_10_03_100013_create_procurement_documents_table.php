<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type');
            $table->string('document_number')->unique();
            $table->date('document_date');
            $table->string('supplier_or_recipient')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('prepared');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['procurement_request_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_documents');
    }
};
