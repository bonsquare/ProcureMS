<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A takeover request no longer names a school: the master chooses the Official Station when approving. */
    public function up(): void
    {
        Schema::table('school_takeover_requests', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->change();
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_takeover_requests', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
