<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('schools')->whereNull('organization_id')->orderBy('id')->get() as $school) {
                $baseSlug = Str::slug($school->name) ?: 'school-'.$school->id;
                $slug = $baseSlug;
                $suffix = 2;

                while (DB::table('organizations')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$suffix++;
                }

                $organizationId = DB::table('organizations')->insertGetId([
                    'name' => $school->name,
                    'slug' => $slug,
                    'status' => $school->status === 'active' ? 'active' : 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('schools')->where('id', $school->id)->update(['organization_id' => $organizationId]);
                $this->seedChartOfAccounts($organizationId);

                foreach (['users', 'procurement_requests', 'liquidation_reports', 'subscriptions', 'audit_logs', 'school_staff', 'suppliers', 'budget_allocations', 'aips'] as $table) {
                    DB::table($table)
                        ->whereNull('organization_id')
                        ->where('school_id', $school->id)
                        ->update(['organization_id' => $organizationId]);
                }
            }

            $this->alignBudgetAccounts();
        });
    }

    private function seedChartOfAccounts(int $organizationId): void
    {
        $now = now();
        $accounts = json_decode(file_get_contents(database_path('chart_of_accounts.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (array_chunk($accounts, 200) as $chunk) {
            DB::table('chart_of_accounts')->insert(array_map(
                fn (array $account) => $account + [
                    'organization_id' => $organizationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $chunk,
            ));
        }
    }

    private function alignBudgetAccounts(): void
    {
        foreach (DB::table('budget_allocations')->whereNotNull('organization_id')->whereNotNull('chart_of_account_id')->get() as $budget) {
            $current = DB::table('chart_of_accounts')->find($budget->chart_of_account_id);
            if (! $current || (int) $current->organization_id === (int) $budget->organization_id) {
                continue;
            }

            $tenantAccountId = DB::table('chart_of_accounts')
                ->where('organization_id', $budget->organization_id)
                ->where('code', $current->code)
                ->value('id');

            if ($tenantAccountId) {
                DB::table('budget_allocations')->where('id', $budget->id)->update([
                    'chart_of_account_id' => $tenantAccountId,
                    'uacs_code' => $current->code,
                    'particulars' => $current->title,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Organizations created here may already own financial records. They are
        // intentionally retained so rolling back code never deletes tenant data.
    }
};
