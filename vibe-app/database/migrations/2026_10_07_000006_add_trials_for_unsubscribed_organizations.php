<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('organizations')->orderBy('id')->get() as $organization) {
            if (DB::table('subscriptions')->where('organization_id', $organization->id)->exists()) {
                continue;
            }

            $schoolId = DB::table('schools')->where('organization_id', $organization->id)->orderBy('id')->value('id');
            if (! $schoolId) {
                continue;
            }

            DB::table('subscriptions')->insert([
                'organization_id' => $organization->id,
                'school_id' => $schoolId,
                'plan' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => 0,
                'payment_status' => 'pending',
                'status' => 'trial',
                'starts_at' => now()->toDateString(),
                'subscription_end' => now()->addDays(30)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Trial records are retained to avoid removing subscription history.
    }
};
