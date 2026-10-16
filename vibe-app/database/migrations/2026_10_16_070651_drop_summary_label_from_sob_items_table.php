<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The SOB summary stays per actual account title (the Budget uses the same accounts); the label column added a few minutes earlier is never used. */
    public function up(): void
    {
        if (Schema::hasColumn('sob_items', 'summary_label')) {
            Schema::table('sob_items', function (Blueprint $table) {
                $table->dropColumn('summary_label');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->string('summary_label', 120)->nullable();
        });
    }
};
