<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Who signs the Purchase Request, when the user chose someone other than the School Settings default. */
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->json('signatories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn('signatories');
        });
    }
};
