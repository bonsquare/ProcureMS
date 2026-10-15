<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The SOB activity a Purchase Request is made for (the SOB items of that activity are the basis of its items). */
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('aip_activity_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn('aip_activity_id');
        });
    }
};
