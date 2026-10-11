<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREFIX = 'SIP source of fund: ';

    /** Earlier AIPs kept the SIP's funds as a remark; they belong in the AIP's Source of Fund, and Remarks starts empty. */
    public function up(): void
    {
        DB::table('aip_activities')->whereNull('source_of_fund')->whereNotNull('remarks_list')->get(['id', 'remarks_list'])->each(function ($row) {
            $remarks = json_decode($row->remarks_list, true);
            if (! is_array($remarks)) {
                return;
            }
            $fund = null;
            $kept = [];
            foreach ($remarks as $remark) {
                if (is_string($remark) && str_starts_with($remark, self::PREFIX)) {
                    $fund = substr($remark, strlen(self::PREFIX));
                } else {
                    $kept[] = $remark;
                }
            }
            if ($fund !== null) {
                DB::table('aip_activities')->where('id', $row->id)->update(['source_of_fund' => $fund, 'remarks_list' => json_encode($kept)]);
            }
        });
    }

    public function down(): void
    {
        // The data move is not reversed.
    }
};
