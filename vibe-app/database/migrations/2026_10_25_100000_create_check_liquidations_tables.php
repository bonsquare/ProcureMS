<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The actual expenses of one check issued as an advance: the check is paid first (Dr Advances, Cr Cash), and this recognizes the expense on liquidation.
        Schema::create('check_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('liquidation_report_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->decimal('check_amount', 15, 2);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->string('refund_reference')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('check_liquidation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('check_liquidation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('account_code', 50);
            $table->string('account_title');
            $table->decimal('amount', 15, 2);
            $table->string('source', 20)->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_liquidation_lines');
        Schema::dropIfExists('check_liquidations');
    }
};
