<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Old starter accounts → the equivalent account in the Revised Chart of Accounts. */
    private const REMAP = [
        '1-06-05-020' => '5-06-04-050',
        '1-06-05-030' => '5-06-04-050',
        '1-06-05-050' => '5-06-04-070',
        '1-06-04-010' => '5-06-04-070',
    ];

    public function up(): void
    {
        $standard = collect(ChartOfAccount::standardAccounts())->keyBy('code');
        $organizations = DB::table('chart_of_accounts')->distinct()->pluck('organization_id');
        $now = now();

        foreach ($organizations as $organizationId) {
            $existing = DB::table('chart_of_accounts')->where('organization_id', $organizationId)->get()->keyBy('code');

            foreach ($standard as $code => $account) {
                if ($existing->has($code)) {
                    DB::table('chart_of_accounts')->where('id', $existing[$code]->id)->update(['title' => $account['title'], 'category' => $account['category'], 'updated_at' => $now]);
                } else {
                    DB::table('chart_of_accounts')->insert($account + ['organization_id' => $organizationId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }

            // Retire starter accounts that are not in the new chart: move their budget items, then drop the unused rows.
            foreach ($existing as $code => $old) {
                if ($standard->has($code)) {
                    continue;
                }

                $target = DB::table('chart_of_accounts')->where('organization_id', $organizationId)->where('code', self::REMAP[$code] ?? null)->first();
                if ($target) {
                    DB::table('budget_allocations')->where('chart_of_account_id', $old->id)->update(['chart_of_account_id' => $target->id, 'uacs_code' => $target->code, 'particulars' => $target->title]);
                }
                if (!DB::table('budget_allocations')->where('chart_of_account_id', $old->id)->exists()) {
                    DB::table('chart_of_accounts')->where('id', $old->id)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // The previous starter list is not restored.
    }
};
