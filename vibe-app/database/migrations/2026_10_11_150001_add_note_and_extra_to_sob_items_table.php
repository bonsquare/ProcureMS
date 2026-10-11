<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** note: free text for the whole Item (e.g. "POW required"); is_extra: an "other line" the user names (e.g. "35% Labor") instead of a purchased particulars. */
    public function up(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->string('note', 255)->nullable();
            $table->boolean('is_extra')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->dropColumn(['note', 'is_extra']);
        });
    }
};
