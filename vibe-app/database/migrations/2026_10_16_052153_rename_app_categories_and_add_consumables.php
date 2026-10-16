<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The categories of the SOB lines, their APP lines and the APP-CSE lines written from the SOB, under their new names. */
    private const RENAMED = [
        'Common Office Supply' => 'Common Office Supplies',
        'Drugs and Medicine' => 'Drugs and Medicines',
        'Construction Components & Supplies' => 'Construction Components and Supplies',
        'Paper and Products' => 'Paper Materials and Products',
        'Common ICT Equipments' => 'Common ICT Equipment',
        'Other categories' => 'Other Categories',
    ];

    public function up(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('sob_items')->where('category', $old)->update(['category' => $new]);
            DB::table('app_items')->where('category', $old)->update(['category' => $new]);
            DB::table('app_cse_items')->where('from_sob', true)->where('category', $old)->update(['category' => $new]);
        }
        // Before the "-" choice, a line without a category (and not an other line) was filed under Other Categories in the APP: it stays there.
        DB::table('sob_items')->whereNull('category')->where('is_extra', false)->update(['category' => 'Other Categories']);
    }

    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('sob_items')->where('category', $new)->update(['category' => $old]);
            DB::table('app_items')->where('category', $new)->update(['category' => $old]);
            DB::table('app_cse_items')->where('from_sob', true)->where('category', $new)->update(['category' => $old]);
        }
    }
};
