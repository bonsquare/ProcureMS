<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Telegram topic opened for a request when it is taken (null while it has none). */
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_thread_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropIndex(['telegram_thread_id']);
            $table->dropColumn('telegram_thread_id');
        });
    }
};
