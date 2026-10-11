<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One line of an APP-CSE: a catalog item (Part I) or the school's own item (Part II), with its price snapshot and 12 monthly quantities. */
    public function up(): void
    {
        Schema::create('app_cse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_cse_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cse_catalog_item_id')->nullable()->constrained('cse_catalog_items')->restrictOnDelete();
            $table->string('category')->nullable();
            $table->text('description');
            $table->string('unit', 50);
            $table->decimal('unit_price', 14, 2)->default(0);
            foreach (range(1, 12) as $month) {
                $table->decimal('m'.$month, 12, 2)->default(0);
            }
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['app_cse_plan_id', 'cse_catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cse_items');
    }
};
