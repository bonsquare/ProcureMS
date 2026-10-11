<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What the last approval wrote to the budget lines: {fund, quarter, groups: ["accountId|program", ...]}, so a revision can release what it no longer includes. */
    public function up(): void
    {
        Schema::table('sob_plans', function (Blueprint $table) {
            $table->json('allotment_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sob_plans', function (Blueprint $table) {
            $table->dropColumn('allotment_snapshot');
        });
    }
};
