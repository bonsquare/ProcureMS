<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dv_breakdown_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('liquidation_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('account_code', 50);
            $table->string('account_title');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['liquidation_report_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dv_breakdown_lines');
    }
};
