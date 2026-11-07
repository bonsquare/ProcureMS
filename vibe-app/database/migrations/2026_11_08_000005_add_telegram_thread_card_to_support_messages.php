<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The header message at the top of a request's topic (its status, assignee and buttons), kept up to date by the bot. */
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_thread_card_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('telegram_thread_card_id');
        });
    }
};
