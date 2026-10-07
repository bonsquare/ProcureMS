<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_number', 50);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('planning');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'transaction_number']);
            $table->index(['organization_id', 'fiscal_year', 'status']);
        });

        Schema::create('transaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 50);
            $table->string('action', 100);
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'master_transaction_id', 'created_at']);
        });

        Schema::create('sip_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('school_year');
            $table->string('planning_period')->nullable();
            $table->string('goal');
            $table->string('objective')->nullable();
            $table->string('project');
            $table->text('activity')->nullable();
            $table->text('expected_output')->nullable();
            $table->string('target')->nullable();
            $table->string('performance_indicator')->nullable();
            $table->string('implementation_schedule')->nullable();
            $table->string('responsible_person')->nullable();
            $table->decimal('estimated_budget', 15, 2)->default(0);
            $table->string('fund_source')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'school_id', 'school_year']);
        });

        Schema::create('ppmp_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('master_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('project_title');
            $table->string('procurement_mode')->nullable();
            $table->string('procurement_schedule')->nullable();
            $table->string('fund_source')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'school_id', 'fiscal_year', 'status']);
        });

        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppmp_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aip_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chart_of_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procurement_item');
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 50)->default('lot');
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('estimated_total_cost', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('app_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'school_id', 'fiscal_year']);
        });

        Schema::create('app_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppmp_item_id')->constrained()->cascadeOnDelete();
            $table->string('procurement_item');
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->decimal('estimated_unit_cost', 15, 2);
            $table->decimal('estimated_total_cost', 15, 2);
            $table->string('procurement_mode')->nullable();
            $table->string('procurement_schedule')->nullable();
            $table->string('fund_source')->nullable();
            $table->string('status')->default('planned');
            $table->timestamps();
            $table->unique(['app_plan_id', 'ppmp_item_id']);
        });

        Schema::create('fund_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'year']);
        });

        foreach (['aips', 'budget_allocations', 'procurement_requests', 'liquidation_reports'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->foreignId('master_transaction_id')->nullable()->after('organization_id')->constrained()->nullOnDelete());
        }

        Schema::table('aips', fn (Blueprint $table) => $table->foreignId('sip_project_id')->nullable()->after('organization_id')->constrained()->nullOnDelete());

        DB::table('organizations')->whereNotNull('fiscal_year')->orderBy('id')->each(function ($organization) {
            DB::table('fiscal_years')->insertOrIgnore([
                'organization_id' => $organization->id,
                'year' => $organization->fiscal_year,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $defaults = ['MOOE', 'SEF', 'IGP', 'Others'];
            foreach ($defaults as $fund) {
                DB::table('fund_sources')->insertOrIgnore([
                    'organization_id' => $organization->id,
                    'code' => strtoupper(str_replace([' ', '-'], '_', $fund)),
                    'name' => $fund,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        foreach (DB::table('aips')->orderBy('id')->get() as $aip) {
            $transactionId = DB::table('master_transactions')->insertGetId([
                'organization_id' => $aip->organization_id,
                'school_id' => $aip->school_id,
                'transaction_number' => sprintf('TXN-%d-%06d', $aip->fiscal_year, $aip->id),
                'fiscal_year' => $aip->fiscal_year,
                'title' => 'AIP FY '.$aip->fiscal_year,
                'description' => 'Master transaction created while linking existing planning and budget records.',
                'status' => $aip->status === 'approved' ? 'budget' : 'planning',
                'created_by' => $aip->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('aips')->where('id', $aip->id)->update(['master_transaction_id' => $transactionId]);
            DB::table('budget_allocations')->where('aip_id', $aip->id)->update(['master_transaction_id' => $transactionId]);
            foreach (DB::table('budget_allocations')->where('aip_id', $aip->id)->pluck('id') as $budgetId) {
                DB::table('procurement_requests')->where('budget_allocation_id', $budgetId)->update(['master_transaction_id' => $transactionId]);
                DB::table('liquidation_reports')->where('budget_allocation_id', $budgetId)->update(['master_transaction_id' => $transactionId]);
            }
            DB::table('transaction_events')->insert([
                'organization_id' => $aip->organization_id,
                'master_transaction_id' => $transactionId,
                'user_id' => $aip->created_by,
                'module' => 'aip',
                'action' => 'legacy_aip_linked',
                'new_status' => $aip->status,
                'remarks' => 'Existing AIP and related allotments connected to a master transaction.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('master_transactions')->orderBy('id')->get()->groupBy(fn ($transaction) => $transaction->organization_id.'|'.$transaction->fiscal_year)->each(function ($transactions) {
            $first = $transactions->first();
            $lastNumber = $transactions->map(function ($transaction) {
                return preg_match('/-(\d+)$/', $transaction->transaction_number, $matches) ? (int) $matches[1] : 0;
            })->max() ?? 0;
            DB::table('document_counters')->updateOrInsert([
                'organization_id' => $first->organization_id,
                'document_type' => 'master_transaction',
                'fiscal_year' => $first->fiscal_year,
            ], [
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('document_counters')->where('document_type', 'master_transaction')->delete();
        foreach (['aips', 'budget_allocations', 'procurement_requests', 'liquidation_reports', 'ppmp_plans'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('master_transaction_id'));
        }
        Schema::table('aips', fn (Blueprint $table) => $table->dropConstrainedForeignId('sip_project_id'));
        foreach (['fiscal_years', 'fund_sources', 'app_items', 'app_plans', 'ppmp_items', 'ppmp_plans', 'sip_projects', 'transaction_events', 'master_transactions'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
