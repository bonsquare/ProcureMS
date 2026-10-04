<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\LiquidationReport;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $schools = collect([
            ['code' => 'SCH-8921', 'name' => 'St. Vincent Academy', 'address' => 'Boston, MA'],
            ['code' => 'SCH-8922', 'name' => 'Global High Institute', 'address' => 'Austin, TX'],
            ['code' => 'SCH-8923', 'name' => 'Metro Polytechnic College', 'address' => 'Chicago, IL'],
            ['code' => 'SCH-8924', 'name' => 'Riverbend Hills University', 'address' => 'Seattle, WA'],
            ['code' => 'SCH-8925', 'name' => 'Oakridge Arts & Sciences', 'address' => 'Denver, CO'],
        ])->map(fn (array $school) => School::updateOrCreate(['code' => $school['code']], $school));

        $admin = User::updateOrCreate(
            ['email' => 'admin@procurems.test'],
            ['name' => 'Master Admin', 'password' => Hash::make('password'), 'role' => 'master_user']
        );

        foreach ($schools as $school) {
            $user = User::updateOrCreate(
                ['email' => 'admin@' . strtolower(str_replace(' ', '', $school->name)) . '.test'],
                ['name' => $school->name . ' Admin', 'password' => Hash::make('password'), 'role' => 'school_admin', 'school_id' => $school->id]
            );

            $request = ProcurementRequest::updateOrCreate(
                ['request_number' => 'PR-' . $school->code],
                ['school_id' => $school->id, 'requested_by' => $user->id, 'title' => 'Office supplies and equipment', 'description' => 'Initial seeded procurement request', 'amount' => 48500, 'status' => 'pending_approval', 'requested_at' => now()]
            );

            LiquidationReport::updateOrCreate(
                ['report_number' => 'LR-' . $school->code],
                ['school_id' => $school->id, 'procurement_request_id' => $request->id, 'submitted_by' => $user->id, 'amount' => 48500, 'status' => 'for_review', 'submitted_at' => now()]
            );

            Subscription::updateOrCreate(
                ['school_id' => $school->id],
                ['plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 12000, 'status' => 'active', 'starts_at' => now()->startOfMonth(), 'renews_at' => now()->addMonth()->startOfMonth()]
            );
        }

        AuditLog::create(['user_id' => $admin->id, 'action' => 'seeded_system_data', 'metadata' => ['source' => 'DatabaseSeeder']]);
    }
}
