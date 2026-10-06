<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 5-02-03-010-01 → 5020301001, and an 8-digit RCA code gets the 00 sub-object (5-02-03-010 → 5020301000). */
    private function toObjectCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $digits = str_replace('-', '', $code);

        return ctype_digit($digits) && strlen($digits) === 8 ? $digits . '00' : $digits;
    }

    public function up(): void
    {
        foreach (DB::table('chart_of_accounts')->get() as $account) {
            DB::table('chart_of_accounts')->where('id', $account->id)->update(['code' => $this->toObjectCode($account->code)]);
        }
        foreach (DB::table('budget_allocations')->whereNotNull('uacs_code')->get() as $item) {
            DB::table('budget_allocations')->where('id', $item->id)->update(['uacs_code' => $this->toObjectCode($item->uacs_code)]);
        }
    }

    public function down(): void
    {
        // Dashed codes are not restored.
    }
};
