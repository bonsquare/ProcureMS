<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_reports', function (Blueprint $table) {
            // The kind of expense that decides which papers are needed: goods, direct, travel or other. Empty: goods when the ORS has a PR, else direct.
            $table->string('ors_kind', 20)->nullable();
        });

        Schema::create('liquidation_document_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('liquidation_report_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 80);
            $table->string('state', 20); // received | not_required (no row: missing)
            $table->string('note', 255)->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['liquidation_report_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidation_document_checks');
        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropColumn('ors_kind');
        });
    }
};
