<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('from_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('to_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->json('proposed_school')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('school_staff', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropColumn('ended_at');
        });
        Schema::dropIfExists('station_transfer_requests');
    }
};
