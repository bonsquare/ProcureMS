<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Adds the asset, liability, equity and revenue accounts to charts that only hold expense accounts. Existing rows are left alone. */
    public function up(): void
    {
        $standard = ChartOfAccount::standardAccounts();
        $now = now();

        foreach (DB::table('chart_of_accounts')->distinct()->pluck('organization_id') as $organizationId) {
            $have = DB::table('chart_of_accounts')->where('organization_id', $organizationId)->pluck('code')->flip();
            $missing = array_filter($standard, fn (array $account) => !$have->has($account['code']));

            foreach (array_chunk($missing, 200) as $chunk) {
                DB::table('chart_of_accounts')->insert(array_map(
                    fn (array $account) => $account + ['organization_id' => $organizationId, 'created_at' => $now, 'updated_at' => $now],
                    $chunk
                ));
            }
        }

        DB::table('chart_of_accounts')->where('code', '1990101000')->where('category', 'MOOE')->update(['category' => 'Assets']);
    }

    public function down(): void
    {
        // Added accounts are not removed.
    }
};
