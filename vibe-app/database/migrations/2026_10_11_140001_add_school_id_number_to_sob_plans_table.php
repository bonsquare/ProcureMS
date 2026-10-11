<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The School ID printed on the SOB, typed by hand for each SOB; blank prints the school profile's ID. */
    public function up(): void
    {
        Schema::table('sob_plans', function (Blueprint $table) {
            $table->string('school_id_number', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sob_plans', function (Blueprint $table) {
            $table->dropColumn('school_id_number');
        });
    }
};
