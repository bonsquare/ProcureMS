<?php

use App\Support\SupportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('type', 20)->default('general');
            $table->string('subject', 120)->nullable();
            $table->string('assigned_name', 80)->nullable();
            $table->unsignedBigInteger('assigned_telegram_id')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
        });
        Schema::table('support_replies', function (Blueprint $table) {
            $table->string('sender', 10)->default('staff');
            $table->unsignedBigInteger('user_id')->nullable();
        });
        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('support_reply_id')->nullable()->index();
            $table->unsignedBigInteger('user_id');
            // Only the link: the file itself stays in the sender's own Google Drive.
            $table->string('drive_file_id', 120);
            $table->string('name', 180);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        foreach (['open', 'answered', 'closed'] as $old) {
            DB::table('support_messages')->where('status', $old)->update(['status' => SupportStatus::fromLegacy($old)]);
        }
        DB::table('support_messages')->where('status', 'resolved')->update(['resolved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::table('support_replies', fn (Blueprint $table) => $table->dropColumn(['sender', 'user_id']));
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn(['type', 'subject', 'assigned_name', 'assigned_telegram_id', 'resolved_at']));
    }
};
