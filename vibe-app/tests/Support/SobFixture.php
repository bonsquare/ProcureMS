<?php

namespace Tests\Support;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\ChartOfAccount;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;

/** A school with an approved AIP (FY 2026) whose activities have a quarter amount, ready for an SOB. */
class SobFixture
{
    /**
     * @param  array{status?: string, activityCount?: int, quarterAmount?: float, slug?: string}  $options
     * @return array{organization: Organization, school: School, user: User, aip: Aip, kra: AipKra, activities: array<int, AipActivity>, account: ChartOfAccount}
     */
    public static function make(array $options = []): array
    {
        $slug = $options['slug'] ?? uniqid('sob');
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => $options['status'] ?? 'approved']);
        $kra = AipKra::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'pillar' => 'Access', 'kra' => 'KRA 3', 'program' => 'Program One']);

        $activities = [];
        foreach (range(1, $options['activityCount'] ?? 2) as $number) {
            $activities[] = AipActivity::create([
                'organization_id' => $organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Activity '.$number, 'physical_target' => 1,
                'q1_amount' => $options['quarterAmount'] ?? 10000, 'q2_amount' => $options['quarterAmount'] ?? 10000, 'q3_amount' => 0, 'q4_amount' => 0,
            ]);
        }
        $account = ChartOfAccount::create(['organization_id' => $organization->id, 'code' => '5-02-03-010', 'title' => 'Office Supplies Expenses', 'category' => 'Expense']);

        return compact('organization', 'school', 'user', 'aip', 'kra', 'activities', 'account');
    }
}
