<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $schools = collect([
            ['code' => 'SCH-8921', 'name' => 'St. Vincent Academy', 'address' => 'Boston, MA'],
            ['code' => 'SCH-8922', 'name' => 'Lubas Elementary School', 'address' => 'Lubas, Kidapawan City, Cotabato'],
            ['code' => 'SCH-8923', 'name' => 'Metro Polytechnic College', 'address' => 'Chicago, IL'],
            ['code' => 'SCH-8924', 'name' => 'Riverbend Hills University', 'address' => 'Seattle, WA'],
            ['code' => 'SCH-8925', 'name' => 'Oakridge Arts & Sciences', 'address' => 'Denver, CO'],
            ['code' => 'SCH-TEST', 'name' => 'Test School', 'address' => 'Test City'],
        ])->map(function (array $schoolData) {
            $organization = Organization::updateOrCreate(
                ['slug' => Str::slug($schoolData['name'])],
                ['name' => $schoolData['name'], 'status' => 'active'],
            );
            if (! $organization->organization_code) {
                $organization->update([
                    'organization_code' => sprintf('ORG-%06d', $organization->id),
                    'fiscal_year' => now()->year,
                ]);
            }

            return School::updateOrCreate(
                ['code' => $schoolData['code']],
                $schoolData + ['organization_id' => $organization->id],
            );
        });

        $admin = User::updateOrCreate(
            ['email' => 'admin@procurems.test'],
            ['name' => 'Master Admin', 'password' => Hash::make('password'), 'role' => 'master_user']
        );

        foreach ($schools as $school) {
            $email = $school->code === 'SCH-TEST'
                ? 'demo@gmail.com'
                : 'admin@'.strtolower(str_replace(' ', '', $school->name)).'.test';

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $school->name.' Admin', 'password' => Hash::make('password'), 'role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]
            );

            $request = ProcurementRequest::updateOrCreate(
                ['request_number' => 'PR-'.$school->code],
                ['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'title' => 'Office supplies and equipment', 'description' => 'Initial seeded procurement request', 'amount' => 48500, 'status' => 'pending_approval', 'requested_at' => now()]
            );

            LiquidationReport::updateOrCreate(
                ['report_number' => 'LR-'.$school->code],
                ['organization_id' => $school->organization_id, 'school_id' => $school->id, 'procurement_request_id' => $request->id, 'submitted_by' => $user->id, 'amount' => 48500, 'status' => 'for_review', 'submitted_at' => now()]
            );

            Subscription::updateOrCreate(
                ['school_id' => $school->id],
                ['organization_id' => $school->organization_id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 12000, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->startOfMonth(), 'subscription_end' => now()->addMonth()->startOfMonth(), 'renews_at' => now()->addMonth()->startOfMonth()]
            );
        }

        $this->seedSampleBudget(School::where('code', 'SCH-TEST')->first());
        $this->call(LubasTestSupplierSeeder::class);
        $this->call(CompleteWorkflowDemoSeeder::class);
        $this->call(PlanningDemoSeeder::class);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'seeded_system_data', 'metadata' => ['source' => 'DatabaseSeeder']]);
    }

    /** FY 2026 sample line: Accounting Section, MOOE, Office Supplies Expenses, ₱40,000 obligated and ₱30,000 liquidated. */
    private function seedSampleBudget(?School $school): void
    {
        if (! $school) {
            return;
        }

        ChartOfAccount::ensureDefaults($school->organization_id);
        $account = ChartOfAccount::withoutGlobalScopes()->where('organization_id', $school->organization_id)->where('code', '5020301000')->first();
        $user = User::where('school_id', $school->id)->first();

        $line = BudgetAllocation::updateOrCreate(
            ['budget_ref_no' => 'BA-2026-SAMPLE'],
            [
                'organization_id' => $school->organization_id, 'school_id' => $school->id, 'office' => 'Accounting Section', 'fiscal_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
                'source_of_fund' => 'MOOE', 'chart_of_account_id' => $account?->id, 'uacs_code' => '5020301000', 'particulars' => $account?->title ?? 'Office Supplies Expenses',
                'amount' => 100000, 'q1_amount' => 25000, 'q2_amount' => 25000, 'q3_amount' => 25000, 'q4_amount' => 25000, 'created_by' => $user?->id,
            ]
        );

        $request = ProcurementRequest::updateOrCreate(
            ['request_number' => 'PR-2026-SAMPLE'],
            ['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user?->id, 'title' => 'Office supplies for Accounting Section', 'description' => 'Sample obligation against the sample budget line', 'amount' => 40000, 'source_of_fund' => 'MOOE', 'status' => 'submitted', 'requested_at' => now(), 'budget_allocation_id' => $line->id]
        );

        LiquidationReport::updateOrCreate(
            ['report_number' => 'LR-2026-SAMPLE'],
            ['organization_id' => $school->organization_id, 'school_id' => $school->id, 'procurement_request_id' => $request->id, 'submitted_by' => $user?->id, 'ors_number' => 'ORS-2026-SAMPLE', 'source_of_fund' => 'MOOE', 'purpose' => 'Office supplies for Accounting Section', 'amount' => 30000, 'status' => 'approved', 'submitted_at' => now(), 'approved_at' => now()]
        );
    }
}
