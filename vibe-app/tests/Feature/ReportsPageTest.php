<?php

namespace Tests\Feature;

use App\Http\Controllers\BudgetAllocationController;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** B-018 (owner's decision D13): the Reports page shows no sample figures, only links to the real reports the role can open. */
class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(string $role): User
    {
        auth()->logout();
        $key = uniqid('rp');
        $organization = Organization::create(['name' => $key, 'slug' => $key, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($key), 'name' => 'Reports School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    public function test_the_page_shows_none_of_the_old_sample_content(): void
    {
        $html = $this->actingAs($this->userOf('school_admin'))->get(route('reports'))->assertOk()->getContent();

        foreach (['4.82M', '3.96M', '860K', '4.2 days', 'October 2025', 'Export Report', 'Custom Report', 'Q3 Accounting Compliance.pdf', 'Riverbend Hills University', 'Recent Exports', 'Procurement Expenditure Report', 'Supplier Performance Report'] as $sample) {
            $this->assertStringNotContainsString($sample, $html, $sample);
        }
    }

    public function test_a_school_admin_sees_a_link_to_every_real_report_and_they_open(): void
    {
        $user = $this->userOf('school_admin');
        $response = $this->actingAs($user)->get(route('reports'))->assertOk();

        foreach (BudgetAllocationController::REPORTS as $type => $title) {
            $response->assertSee($title)->assertSee(route('budget.allocation.report', $type), false);
        }
        $response->assertSee('Allotment Registry')->assertSee(route('allotment-registry'), false)
            ->assertSee('Chart of Accounts')->assertSee(route('chart-of-accounts'), false)
            ->assertSee('Accounting: ORS and DV')->assertSee(route('accounting'), false)
            ->assertSee('Cash reports')->assertSee(route('cash', ['tab' => 'reports']), false)
            ->assertSee('Annual Implementation Plan (AIP)');

        $this->get(route('budget.allocation.report', 'annual'))->assertOk();
        $this->get(route('allotment-registry'))->assertOk();
        $this->get(route('chart-of-accounts'))->assertOk();
        $this->get(route('accounting'))->assertOk();
        $this->get(route('cash', ['tab' => 'reports']))->assertOk();
    }

    public function test_a_viewer_sees_only_the_reports_the_role_can_open(): void
    {
        $viewer = $this->userOf('viewer');
        $this->assertTrue($viewer->hasPermission('budget.view'));
        $this->assertFalse($viewer->hasPermission('accounting.view'));
        $this->assertFalse($viewer->hasPermission('cash.pay'));

        $this->actingAs($viewer)->get(route('reports'))->assertOk()
            ->assertSee('Annual Budget Allocation Report')->assertSee('Allotment Registry')
            ->assertDontSee('The accounts of the organization and where they are used.')->assertDontSee('Accounting: ORS and DV')->assertDontSee('Cash reports');
    }

    public function test_a_role_with_no_report_gets_a_plain_message(): void
    {
        $user = $this->userOf('approver');
        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('budget.view'));
        $this->assertFalse($user->hasPermission('planning.view'));

        $this->actingAs($user)->get(route('reports'))->assertOk()->assertSee('No reports are available for your role.');
    }
}
