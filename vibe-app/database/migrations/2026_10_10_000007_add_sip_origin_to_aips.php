<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aips', function (Blueprint $table) {
            // Which SIP plan period and which of its three years an AIP was generated from (null for an AIP entered by hand).
            $table->unsignedSmallInteger('sip_start_year')->nullable();
            $table->unsignedTinyInteger('sip_year_no')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aips', function (Blueprint $table) {
            $table->dropColumn(['sip_start_year', 'sip_year_no']);
        });
    }
};
