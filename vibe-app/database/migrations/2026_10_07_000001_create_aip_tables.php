<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status')->default('draft');
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->string('noted_by_name')->nullable();
            $table->string('noted_by_position')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'fiscal_year']);
        });

        Schema::create('aip_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aip_id')->constrained()->cascadeOnDelete();
            $table->string('pillar')->nullable();
            $table->string('kra')->nullable();
            $table->text('intermediate_outcome')->nullable();
            $table->string('strategy')->nullable();
            $table->string('five_point_agenda')->nullable();
            $table->string('program');
            $table->text('activity');
            $table->unsignedInteger('physical_target')->default(1);
            $table->string('timeline')->nullable();
            $table->decimal('q1_amount', 15, 2)->default(0);
            $table->decimal('q2_amount', 15, 2)->default(0);
            $table->decimal('q3_amount', 15, 2)->default(0);
            $table->decimal('q4_amount', 15, 2)->default(0);
            $table->string('source_of_fund')->nullable();
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('responsible_person')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->foreignId('aip_id')->nullable()->after('school_id')->constrained('aips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropConstrainedForeignId('aip_id'));
        Schema::dropIfExists('aip_activities');
        Schema::dropIfExists('aips');
    }
};
