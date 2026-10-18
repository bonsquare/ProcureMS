<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            // The Procurement Service catalog item picked for this particulars: such a line goes to Part I of the APP-CSE, every other line to Part II.
            $table->unsignedBigInteger('cse_catalog_item_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->dropColumn('cse_catalog_item_id');
        });
    }
};
