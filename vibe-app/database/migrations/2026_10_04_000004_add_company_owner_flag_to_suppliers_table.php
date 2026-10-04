<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('has_company_owner')->default(false)->after('addressee');
        });

        DB::table('suppliers')
            ->whereNotNull('owner_given_name')
            ->orWhereNotNull('owner_last_name')
            ->update(['has_company_owner' => true]);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('has_company_owner');
        });
    }
};
