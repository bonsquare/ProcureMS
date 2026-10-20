<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An ORS charged to more than one budget line (UACS code), each with its own amount. An ORS with one line has no rows here.
        Schema::create('liquidation_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('liquidation_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_allocation_id')->constrained();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->index('budget_allocation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidation_report_lines');
    }
};
