<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aips', function (Blueprint $table) {
            $table->string('entity')->default('School')->after('fiscal_year');
        });

        // Pillar → KRA → Activity: the KRA block holds the shared fields, activities hang under it.
        Schema::create('aip_kras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aip_id')->constrained()->cascadeOnDelete();
            $table->string('pillar')->nullable();
            $table->string('kra');
            $table->text('intermediate_outcome')->nullable();
            $table->string('strategy')->nullable();
            $table->string('five_point_agenda')->nullable();
            $table->string('program')->nullable();
            $table->timestamps();
        });

        Schema::table('aip_activities', function (Blueprint $table) {
            $table->foreignId('aip_kra_id')->nullable()->after('aip_id')->constrained('aip_kras')->cascadeOnDelete();
            $table->json('responsible_persons')->nullable();
            $table->json('remarks_list')->nullable();
        });

        $blockKeys = ['pillar', 'kra', 'intermediate_outcome', 'strategy', 'five_point_agenda', 'program'];
        $blocks = [];
        foreach (DB::table('aip_activities')->orderBy('id')->get() as $activity) {
            $key = $activity->aip_id . '|' . implode('|', array_map(fn ($k) => (string) $activity->$k, $blockKeys));
            $blocks[$key] ??= DB::table('aip_kras')->insertGetId([
                'aip_id' => $activity->aip_id, 'pillar' => $activity->pillar, 'kra' => $activity->kra ?: 'Unspecified KRA', 'intermediate_outcome' => $activity->intermediate_outcome,
                'strategy' => $activity->strategy, 'five_point_agenda' => $activity->five_point_agenda, 'program' => $activity->program, 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('aip_activities')->where('id', $activity->id)->update([
                'aip_kra_id' => $blocks[$key],
                'responsible_persons' => json_encode(array_values(array_filter([$activity->responsible_person]))),
                'remarks_list' => json_encode(array_values(array_filter([$activity->remarks]))),
            ]);
        }

        Schema::table('aip_activities', function (Blueprint $table) {
            $table->dropColumn(['pillar', 'kra', 'intermediate_outcome', 'strategy', 'five_point_agenda', 'program', 'responsible_person', 'remarks']);
        });
    }

    public function down(): void
    {
        // The flat layout is not restored.
    }
};
