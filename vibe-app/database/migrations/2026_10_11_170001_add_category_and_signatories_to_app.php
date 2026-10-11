<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLOTS = ['prepared_by', 'recommended_by', 'approved_by'];

    /** category: where a particulars (and its APP line) is filed; the printed APP takes a typed Role, Name and Position for each signature. */
    public function up(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->string('category')->nullable();
        });
        Schema::table('app_items', function (Blueprint $table) {
            $table->string('category')->nullable();
        });
        Schema::table('app_plans', function (Blueprint $table) {
            foreach (self::SLOTS as $slot) {
                $table->string($slot.'_role')->nullable();
                $table->string($slot.'_name')->nullable();
                $table->string($slot.'_position')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sob_items', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('app_items', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('app_plans', function (Blueprint $table) {
            $columns = [];
            foreach (self::SLOTS as $slot) {
                array_push($columns, $slot.'_role', $slot.'_name', $slot.'_position');
            }
            $table->dropColumn($columns);
        });
    }
};
