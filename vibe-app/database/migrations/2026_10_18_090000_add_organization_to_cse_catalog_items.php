<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cse_catalog_items', function (Blueprint $table) {
            // Null: a Procurement Service catalog item shared by everyone. Set: an item a school's SOB typed by hand for Part I, seen only by that organization.
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            // An item typed by hand sits beside the other items of its category, so it shares their sort order.
            $table->dropUnique(['catalog_year', 'sort_order']);
            $table->index(['catalog_year', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('cse_catalog_items', function (Blueprint $table) {
            $table->dropIndex(['catalog_year', 'sort_order']);
            $table->unique(['catalog_year', 'sort_order']);
            $table->dropColumn('organization_id');
        });
    }
};
