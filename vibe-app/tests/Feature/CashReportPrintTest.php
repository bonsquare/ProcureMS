<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReportPrintTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(string $code, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    public function test_the_reports_tab_shows_the_period_the_fund_entries_and_three_report_buttons(): void
    {
        [$school, $user] = $this->tenant('RT1');

        $html = $this->actingAs($user)->get(route('cash', ['tab' => 'reports']))->assertOk()->getContent();

        $this->assertStringContainsString('Fund received', $html);
        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register'] as $name) {
            $this->assertStringContainsString(e(route($name, ['school_id' => $school->id])), $html, $name);
        }
        $this->assertStringContainsString(route('cash.fund.store'), $html);
    }

    public function test_the_reports_tab_needs_cash_permission(): void
    {
        [, $viewer] = $this->tenant('RT2', 'viewer');

        $this->actingAs($viewer)->get(route('cash', ['tab' => 'reports']))->assertForbidden();
    }

    public function test_a_school_user_cannot_open_another_schools_report(): void
    {
        [, $userA] = $this->tenant('RTA');
        [$schoolB] = $this->tenant('RTB');

        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register'] as $name) {
            $this->actingAs($userA)->get(route($name, ['school_id' => $schoolB->id, 'from' => '2026-07-01', 'to' => '2026-09-30']))->assertForbidden();
        }
    }

    public function test_the_existing_cash_tabs_still_work(): void
    {
        [, $user] = $this->tenant('RT3');

        foreach (['unpaid', 'paid', 'cancelled'] as $tab) {
            $this->actingAs($user)->get(route('cash', ['tab' => $tab]))->assertOk();
        }
    }
}
