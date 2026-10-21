<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_report_histories', function (Blueprint $table) {
            // The form values of the report when it was saved, so a kept report can be updated from today's figures with the same header.
            $table->json('params')->nullable()->after('html');
        });
    }

    public function down(): void
    {
        Schema::table('cash_report_histories', function (Blueprint $table) {
            $table->dropColumn('params');
        });
    }
};
