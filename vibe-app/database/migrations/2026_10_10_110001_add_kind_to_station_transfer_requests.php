<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_transfer_requests', function (Blueprint $table) {
            // transfer (a move to a school), official_station (the master chooses the school) or other (a typed request).
            $table->string('kind', 20)->default('transfer');
            $table->string('subject')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('station_transfer_requests', function (Blueprint $table) {
            $table->dropColumn(['kind', 'subject']);
        });
    }
};
