<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sip_projects', function (Blueprint $table) {
            $table->string('goal')->nullable()->change();
            $table->string('pillar')->nullable()->after('planning_period');
            $table->string('kra')->nullable()->after('pillar');
            $table->text('organizational_outcome')->nullable()->after('kra');
            $table->string('strategy')->nullable()->after('organizational_outcome');
            $table->string('five_point_agenda')->nullable()->after('strategy');
        });

        Schema::create('sip_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sip_project_id')->constrained()->cascadeOnDelete();
            $table->string('activity', 1000);
            $table->decimal('physical_year1', 12, 2)->nullable();
            $table->decimal('physical_year2', 12, 2)->nullable();
            $table->decimal('physical_year3', 12, 2)->nullable();
            $table->decimal('financial_year1', 15, 2)->default(0);
            $table->decimal('financial_year2', 15, 2)->default(0);
            $table->decimal('financial_year3', 15, 2)->default(0);
            $table->string('source_of_fund')->nullable();
            $table->text('responsible_person')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'sip_project_id']);
        });

        Schema::create('sip_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->string('recommended_by_name')->nullable();
            $table->string('recommended_by_position')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'start_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_plans');
        Schema::dropIfExists('sip_activities');
        Schema::table('sip_projects', function (Blueprint $table) {
            $table->dropColumn(['pillar', 'kra', 'organizational_outcome', 'strategy', 'five_point_agenda']);
        });
    }
};
