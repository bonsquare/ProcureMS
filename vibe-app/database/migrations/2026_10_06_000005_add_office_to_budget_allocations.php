<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->string('office')->nullable()->after('school_id');
            $table->string('responsibility_center')->nullable()->after('fund_name');
            $table->index(['fiscal_year', 'office']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('office')->nullable()->after('position');
        });

        // Existing lines belong to the school's own office until a department is set.
        DB::table('budget_allocations')->whereNull('office')->get()->each(function ($row) {
            DB::table('budget_allocations')->where('id', $row->id)->update(['office' => DB::table('schools')->where('id', $row->school_id)->value('name')]);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('office'));
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropIndex(['fiscal_year', 'office']);
            $table->dropColumn(['office', 'responsibility_center']);
        });
    }
};
