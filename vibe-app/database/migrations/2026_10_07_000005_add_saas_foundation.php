<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('organization_code', 30)->nullable()->after('id');
            $table->unsignedSmallInteger('fiscal_year')->nullable()->after('status');
            $table->string('default_fund_source')->nullable()->after('fiscal_year');
            $table->json('numbering_preferences')->nullable()->after('default_fund_source');
        });

        foreach (DB::table('organizations')->orderBy('id')->get() as $organization) {
            DB::table('organizations')->where('id', $organization->id)->update([
                'organization_code' => sprintf('ORG-%06d', $organization->id),
                'fiscal_year' => now()->year,
            ]);
        }

        Schema::table('organizations', fn (Blueprint $table) => $table->unique('organization_code'));

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('subscription_end')->nullable()->after('renews_at');
            $table->date('grace_period_end')->nullable()->after('subscription_end');
            $table->string('payment_status')->default('pending')->after('amount');
            $table->timestamp('renewed_at')->nullable()->after('grace_period_end');
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'document_type', 'fiscal_year']);
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropUnique(['request_number']);
            $table->unique(['organization_id', 'request_number']);
        });

        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->unique(['organization_id', 'document_number']);
        });

        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropUnique(['report_number']);
            $table->dropUnique(['dv_number']);
            $table->unique(['organization_id', 'report_number']);
            $table->unique(['organization_id', 'ors_number']);
            $table->unique(['organization_id', 'dv_number']);
        });

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->unique(['organization_id', 'budget_ref_no']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropUnique(['organization_id', 'budget_ref_no']));

        Schema::table('liquidation_reports', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'report_number']);
            $table->dropUnique(['organization_id', 'ors_number']);
            $table->dropUnique(['organization_id', 'dv_number']);
            $table->unique('report_number');
            $table->unique('dv_number');
        });

        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'document_number']);
            $table->unique('document_number');
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'request_number']);
            $table->unique('request_number');
        });

        Schema::dropIfExists('document_counters');

        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['subscription_end', 'grace_period_end', 'payment_status', 'renewed_at']));
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['organization_code']);
            $table->dropColumn(['organization_code', 'fiscal_year', 'default_fund_source', 'numbering_preferences']);
        });
    }
};
