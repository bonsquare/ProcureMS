<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->string('document_role')->nullable()->after('procurement_role');
        });
    }

    public function down(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropColumn('document_role');
        });
    }
};
