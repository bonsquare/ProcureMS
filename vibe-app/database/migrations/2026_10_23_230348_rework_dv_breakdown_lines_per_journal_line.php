<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dv_breakdown_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('journal_line_no')->default(1)->after('liquidation_report_id');
            $table->decimal('debit', 15, 2)->default(0)->after('account_title');
            $table->decimal('credit', 15, 2)->default(0)->after('debit');
        });
        // The first version kept one amount per line, all of it a debit of the first journal line.
        DB::table('dv_breakdown_lines')->update(['debit' => DB::raw('amount')]);
        Schema::table('dv_breakdown_lines', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

    public function down(): void
    {
        Schema::table('dv_breakdown_lines', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0);
        });
        DB::table('dv_breakdown_lines')->update(['amount' => DB::raw('debit - credit')]);
        Schema::table('dv_breakdown_lines', function (Blueprint $table) {
            $table->dropColumn(['journal_line_no', 'debit', 'credit']);
        });
    }
};
