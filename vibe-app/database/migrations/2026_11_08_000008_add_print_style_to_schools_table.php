<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The optional table and text style every printed document of the school takes (cell padding, font, text size, line height, border, header and footer spacing); null keeps each form's own.
        Schema::table('schools', function (Blueprint $table) {
            $table->json('print_style')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('print_style');
        });
    }
};
