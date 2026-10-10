<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sob_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('quarter');
            $table->string('fund_source', 100)->default('MOOE');
            $table->string('status', 20)->default('draft');
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->string('recommended_by_name')->nullable();
            $table->string('recommended_by_position')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'fiscal_year', 'quarter', 'fund_source']);
        });

        Schema::create('sob_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sob_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aip_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained()->restrictOnDelete();
            $table->string('particulars');
            $table->decimal('frequency', 12, 2)->default(1);
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::table('app_items', function (Blueprint $table) {
            $table->unsignedBigInteger('ppmp_item_id')->nullable()->change();
        });
        Schema::table('app_items', function (Blueprint $table) {
            $table->foreignId('sob_item_id')->nullable()->after('ppmp_item_id')->constrained('sob_items')->cascadeOnDelete();
            $table->unique(['app_plan_id', 'sob_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('app_items', function (Blueprint $table) {
            $table->dropUnique(['app_plan_id', 'sob_item_id']);
            $table->dropConstrainedForeignId('sob_item_id');
        });
        Schema::dropIfExists('sob_items');
        Schema::dropIfExists('sob_plans');
    }
};
