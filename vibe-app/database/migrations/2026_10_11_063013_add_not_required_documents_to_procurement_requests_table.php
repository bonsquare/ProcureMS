<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Optional documents (ICS, PAR) the user marked "not required for this transaction": { document type: {user_id, name, at} }. */
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->json('not_required_documents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn('not_required_documents');
        });
    }
};
