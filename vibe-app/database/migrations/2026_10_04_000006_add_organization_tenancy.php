<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users', 'schools', 'agency_settings', 'procurement_requests', 'liquidation_reports',
        'subscriptions', 'audit_logs', 'procurement_request_items', 'procurement_documents',
        'suppliers', 'school_staff',
    ];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index('organization_id');
            });
        }

        $schools = DB::table('schools')->orderBy('id')->get();
        $defaultOrganizationId = null;
        foreach ($schools as $school) {
            $organizationId = DB::table('organizations')->insertGetId([
                'name' => $school->name,
                'slug' => 'org-' . $school->id,
                'status' => $school->status === 'active' ? 'active' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $defaultOrganizationId ??= $organizationId;
            DB::table('schools')->where('id', $school->id)->update(['organization_id' => $organizationId]);

            foreach (['users', 'procurement_requests', 'liquidation_reports', 'subscriptions', 'audit_logs', 'school_staff', 'suppliers'] as $tableName) {
                DB::table($tableName)->where('school_id', $school->id)->update(['organization_id' => $organizationId]);
            }
        }

        if (!$defaultOrganizationId) {
            $defaultOrganizationId = DB::table('organizations')->insertGetId([
                'name' => 'Default Organization',
                'slug' => 'default-organization',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('agency_settings')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('procurement_request_items')->whereNull('organization_id')->update([
            'organization_id' => DB::raw('(select organization_id from procurement_requests where procurement_requests.id = procurement_request_items.procurement_request_id)'),
        ]);
        DB::table('procurement_documents')->whereNull('organization_id')->update([
            'organization_id' => DB::raw('(select organization_id from procurement_requests where procurement_requests.id = procurement_documents.procurement_request_id)'),
        ]);
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
        Schema::dropIfExists('organizations');
    }
};
