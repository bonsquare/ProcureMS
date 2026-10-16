<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One material line of a Program of Works (amount = quantity x unit cost). */
    public function up(): void
    {
        Schema::create('pow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pow_plan_id')->constrained('pow_plans')->cascadeOnDelete();
            $table->foreignId('sob_item_id')->nullable()->constrained('sob_items')->nullOnDelete();
            $table->string('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pow_items');
    }
};
