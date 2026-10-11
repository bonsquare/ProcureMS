<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Typed Role (the caption above a signature) for every signature of the planning documents; APP-CSE also gets typed names and positions. Blank keeps the standard caption. */
    private const ROLES = [
        'sip_plans' => ['prepared_by', 'recommended_by', 'approved_by'],
        'aips' => ['prepared_by', 'noted_by', 'approved_by'],
        'sob_plans' => ['prepared_by', 'recommended_by', 'approved_by'],
    ];

    public function up(): void
    {
        foreach (self::ROLES as $table => $slots) {
            Schema::table($table, function (Blueprint $blueprint) use ($slots) {
                foreach ($slots as $slot) {
                    $blueprint->string($slot.'_role')->nullable();
                }
            });
        }
        Schema::table('app_cse_plans', function (Blueprint $blueprint) {
            foreach (['prepared_by', 'noted_by', 'certified_by'] as $slot) {
                $blueprint->string($slot.'_role')->nullable();
                $blueprint->string($slot.'_name')->nullable();
                $blueprint->string($slot.'_position')->nullable();
            }
            $blueprint->string('agency_head_role')->nullable();
        });
    }

    public function down(): void
    {
        foreach (self::ROLES as $table => $slots) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(array_map(fn ($slot) => $slot.'_role', $slots)));
        }
        Schema::table('app_cse_plans', function (Blueprint $blueprint) {
            $columns = ['agency_head_role'];
            foreach (['prepared_by', 'noted_by', 'certified_by'] as $slot) {
                array_push($columns, $slot.'_role', $slot.'_name', $slot.'_position');
            }
            $blueprint->dropColumn($columns);
        });
    }
};
