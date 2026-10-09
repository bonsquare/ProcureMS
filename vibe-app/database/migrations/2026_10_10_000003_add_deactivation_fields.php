<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->string('end_reason', 40)->nullable();
            $table->text('end_note')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 40)->nullable();
            $table->text('deactivation_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason', 'deactivation_note']);
        });

        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropColumn(['end_reason', 'end_note']);
        });
    }
};
