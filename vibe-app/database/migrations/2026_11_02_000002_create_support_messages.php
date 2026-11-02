<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            // The id of the message the bot sent to Telegram: a reply to it there comes back as the answer to this message.
            $table->unsignedBigInteger('telegram_message_id')->nullable()->index();
            $table->string('telegram_status', 20)->default('pending');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_replies');
        Schema::dropIfExists('support_messages');
    }
};
