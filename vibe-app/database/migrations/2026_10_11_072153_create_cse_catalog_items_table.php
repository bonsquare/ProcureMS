<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Procurement Service catalog of the APP-CSE: reference data shared by every school, one set of rows per catalog year. */
    public function up(): void
    {
        Schema::create('cse_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('catalog_year');
            $table->string('group_heading')->nullable();
            $table->string('category');
            $table->string('code', 60);
            $table->text('description');
            $table->string('unit', 50);
            $table->decimal('price', 14, 2)->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->unique(['catalog_year', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cse_catalog_items');
    }
};
