<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelled_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('liquidation_report_id')->constrained();
            $table->string('ors_number')->nullable();
            $table->string('dv_number')->nullable();
            $table->string('check_number', 100)->nullable();
            $table->string('payment_mode')->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->foreignId('cancelled_by')->constrained('users');
            $table->timestamp('cancelled_at');
            $table->timestamps();
        });

        Schema::table('liquidation_reports', function (Blueprint $table) {
            // True from the moment a paid check is cancelled until the ORS is paid again: the ORS, DV and journal entry can be edited.
            $table->boolean('reissue_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('reissue_pending');
        });
        Schema::dropIfExists('cancelled_checks');
    }
};
