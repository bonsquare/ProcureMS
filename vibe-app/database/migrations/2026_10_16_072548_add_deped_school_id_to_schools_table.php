<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** deped_school_id: the school's DepEd School ID number (the `code` column stays the system's own School ID). */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('deped_school_id', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('deped_school_id');
        });
    }
};
