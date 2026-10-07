<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aip_kras', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('aip_activities', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->backfillFromSchool();
        $this->backfillAipChildren();
        $this->alignAccountReferences('budget_allocations');
        $this->alignAccountReferences('aip_activities');
    }

    private function backfillFromSchool(): void
    {
        foreach (['users', 'procurement_requests', 'liquidation_reports', 'subscriptions', 'audit_logs', 'school_staff', 'suppliers', 'budget_allocations', 'aips'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'school_id') || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('organization_id')
                ->whereNotNull('school_id')
                ->update([
                    'organization_id' => DB::raw('(select organization_id from schools where schools.id = '.$table.'.school_id)'),
                ]);
        }

        foreach (['procurement_request_items', 'procurement_documents'] as $table) {
            DB::table($table)
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => DB::raw('(select organization_id from procurement_requests where procurement_requests.id = '.$table.'.procurement_request_id)'),
                ]);
        }
    }

    private function backfillAipChildren(): void
    {
        DB::table('aip_kras')->whereNull('organization_id')->update([
            'organization_id' => DB::raw('(select organization_id from aips where aips.id = aip_kras.aip_id)'),
        ]);

        DB::table('aip_activities')->whereNull('organization_id')->update([
            'organization_id' => DB::raw('(select organization_id from aips where aips.id = aip_activities.aip_id)'),
        ]);
    }

    private function alignAccountReferences(string $table): void
    {
        foreach (DB::table($table)->whereNotNull('organization_id')->whereNotNull('chart_of_account_id')->get() as $record) {
            $current = DB::table('chart_of_accounts')->find($record->chart_of_account_id);
            if (! $current || (int) $current->organization_id === (int) $record->organization_id) {
                continue;
            }

            $tenantAccountId = DB::table('chart_of_accounts')
                ->where('organization_id', $record->organization_id)
                ->where('code', $current->code)
                ->value('id');

            if ($tenantAccountId) {
                DB::table($table)->where('id', $record->id)->update(['chart_of_account_id' => $tenantAccountId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('aip_activities', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('aip_kras', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
    }
};
