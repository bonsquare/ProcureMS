<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The paper every document of the school opens on (a4, letter, legal, longbond or folio); null keeps each document's own paper.
        Schema::table('schools', function (Blueprint $table) {
            $table->string('print_paper', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('print_paper');
        });
    }
};
