<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Who signs the ORS and the DV of this report when the user chose someone other than the School Settings default: ['ors' => [...], 'dv' => [...]]. */
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->json('signatories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('signatories');
        });
    }
};
