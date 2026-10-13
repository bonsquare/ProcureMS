<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_cse_items', function (Blueprint $table) {
            // A line written from the SOB (its Common Office Supply particulars); the SOB keeps it up to date, so it is changed there, not here.
            $table->boolean('from_sob')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('app_cse_items', function (Blueprint $table) {
            $table->dropColumn('from_sob');
        });
    }
};
