<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A DV that remits withholding tax to the BIR, made from an alphalist: the alphalist (history id), the BIR form and the tax of each DV it was withheld from.
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->json('remittance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('remittance');
        });
    }
};
