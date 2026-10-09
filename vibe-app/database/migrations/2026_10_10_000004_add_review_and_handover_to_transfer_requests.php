<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_transfer_requests', function (Blueprint $table) {
            $table->string('review_status', 20)->default('not_required');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('review_expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('handover_ends_at')->nullable();
            $table->foreignId('handover_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handover_ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('station_transfer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_user_id');
            $table->dropConstrainedForeignId('handover_user_id');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note', 'review_expires_at', 'expired_at', 'handover_ends_at', 'handover_ended_at']);
        });
    }
};
