<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** summary_label: the name an Item takes in the printed "Summary per object of expenditure" (rows with the same label are added together). */
    public function up(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->string('summary_label', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sob_items', function (Blueprint $table) {
            $table->dropColumn('summary_label');
        });
    }
};
