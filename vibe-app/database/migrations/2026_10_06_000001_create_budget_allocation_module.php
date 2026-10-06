<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('title');
            $table->string('category');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->string('budget_ref_no')->nullable()->after('created_by');
            $table->string('fund_name')->nullable()->after('source_of_fund');
            $table->string('program')->nullable()->after('fund_name');
            $table->foreignId('chart_of_account_id')->nullable()->after('program')->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description')->nullable()->after('particulars');
            $table->decimal('q1_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('q2_amount', 15, 2)->default(0)->after('q1_amount');
            $table->decimal('q3_amount', 15, 2)->default(0)->after('q2_amount');
            $table->decimal('q4_amount', 15, 2)->default(0)->after('q3_amount');
            $table->date('start_date')->nullable()->after('fiscal_year');
            $table->date('end_date')->nullable()->after('start_date');
            $table->text('remarks')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        // Existing allocations: spread the annual amount evenly across the four quarters.
        DB::table('budget_allocations')->get()->each(function ($row) {
            $quarter = round($row->amount / 4, 2);
            DB::table('budget_allocations')->where('id', $row->id)->update([
                'q1_amount' => $quarter,
                'q2_amount' => $quarter,
                'q3_amount' => $quarter,
                'q4_amount' => round($row->amount - $quarter * 3, 2),
                'start_date' => $row->fiscal_year . '-01-01',
                'end_date' => $row->fiscal_year . '-12-31',
            ]);
        });

        foreach (['procurement_requests', 'liquidation_reports'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('budget_allocation_id')->nullable()->constrained('budget_allocations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['procurement_requests', 'liquidation_reports'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('budget_allocation_id');
            });
        }

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_of_account_id');
            $table->dropColumn(['budget_ref_no', 'fund_name', 'program', 'description', 'q1_amount', 'q2_amount', 'q3_amount', 'q4_amount', 'start_date', 'end_date', 'remarks', 'closed_at']);
        });

        Schema::dropIfExists('chart_of_accounts');
    }
};
