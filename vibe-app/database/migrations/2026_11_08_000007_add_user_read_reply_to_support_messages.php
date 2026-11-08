<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The newest Customer Services answer the user has already read, so the bell tells only about new ones. */
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_read_reply_id')->nullable();
        });

        // Answers that exist today count as read: nobody gets an alert for old replies.
        DB::statement("update support_messages set user_read_reply_id = (select max(id) from support_replies where support_replies.support_message_id = support_messages.id and support_replies.sender = 'staff')");
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('user_read_reply_id');
        });
    }
};
