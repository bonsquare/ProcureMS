<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->foreignId('app_item_id')->nullable()->after('procurement_request_id')->constrained('app_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('app_item_id');
        });
    }
};
