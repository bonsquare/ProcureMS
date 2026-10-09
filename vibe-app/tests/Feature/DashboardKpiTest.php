<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function request(Organization $organization, School $school, User $user, string $number, string $status, float $amount): ProcurementRequest
    {
        return ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => $number, 'title' => 'Supplies '.$number, 'amount' => $amount, 'status' => $status]);
    }

    public function test_school_user_sees_kpis_for_their_own_school_only(): void
    {
        [$organizationA, $schoolA, $userA] = $this->school('kpi-a');
        [$organizationB, $schoolB, $userB] = $this->school('kpi-b');
        $this->request($organizationA, $schoolA, $userA, 'PR-A-1', 'pending_approval', 1000);
        $this->request($organizationA, $schoolA, $userA, 'PR-A-2', 'completed', 3000);
        $this->request($organizationB, $schoolB, $userB, 'PR-B-1', 'submitted', 9000);
        BudgetAllocation::create(['organization_id' => $organizationA->id, 'school_id' => $schoolA->id, 'fiscal_year' => now()->year, 'source_of_fund' => 'MOOE', 'particulars' => 'Supplies', 'amount' => 10000, 'q1_amount' => 2500, 'q2_amount' => 2500, 'q3_amount' => 2500, 'q4_amount' => 2500]);

        $response = $this->actingAs($userA)->get(route('home'))->assertOk()
            ->assertSee('KPI dashboard')->assertSee('Budget utilization')->assertSee('Pending approval')->assertSee('Completion rate')->assertSee('Request pipeline')->assertSee('Budget flow')->assertSee('Needs your attention')->assertSee('Recent requests')->assertSee('Budget by quarter')->assertSee('PR-A-1')->assertSee('School Workspace')->assertSee('School Dashboard')->assertDontSee('My KPI Dashboard')
            ->assertDontSee('School performance');

        $kpi = $response->viewData('kpi');
        $this->assertSame(2, $kpi['procurement']['total']);
        $this->assertSame(1, $kpi['procurement']['pending']);
        $this->assertSame(50, $kpi['procurement']['completion_rate']);
        $this->assertEqualsWithDelta(10000.0, $kpi['budget']['allocated'], 0.01);
    }

    public function test_master_admin_sees_all_schools_with_a_comparison_table(): void
    {
        [$organizationA, $schoolA, $userA] = $this->school('kpi-c');
        [$organizationB, $schoolB, $userB] = $this->school('kpi-d');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->request($organizationA, $schoolA, $userA, 'PR-C-1', 'submitted', 500);
        $this->request($organizationB, $schoolB, $userB, 'PR-D-1', 'approved', 700);

        $this->actingAs($master)->get(route('home'))->assertOk()
            ->assertSee('System Master Dashboard')->assertSee('System KPIs')->assertSee('Audit events')->assertSee('KPI Dashboard')->assertDontSee('Branding Settings')->assertDontSee('Master Account')->assertSee('Total Schools')->assertDontSee('School performance')->assertDontSee('Budget flow');

        $system = $this->actingAs($master)->get(route('home'))->getContent();
        $this->assertLessThan(strpos($system, 'id="schools-section"'), strpos($system, 'aria-label="System KPIs"'), 'System KPIs sit above the school list');
        $this->assertGreaterThan(strpos($system, 'Total Schools'), strpos($system, 'aria-label="System KPIs"'));

        $response = $this->actingAs($master)->get(route('home', ['view' => 'kpi']))->assertOk()
            ->assertSee('KPI dashboard')->assertSee('All schools')->assertSee('School performance')->assertSee('kpi-c School')->assertSee('kpi-d School');

        $this->assertSame(2, $response->viewData('kpi')['procurement']['total']);
        $this->assertCount(2, $response->viewData('kpi')['schools']);
    }

    public function test_school_activation_lives_on_the_subscriptions_page_not_the_dashboard(): void
    {
        [$organization] = $this->school('kpi-e');
        $pending = School::create(['organization_id' => $organization->id, 'code' => 'PEND', 'name' => 'Waiting School', 'status' => 'inactive']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('home'))->assertOk()->assertDontSee('Schools for Activation');
        $this->get(route('subscriptions'))->assertOk()->assertSee('Schools for Activation')->assertSee('Waiting School')->assertSee('name="redirect_to" value="subscriptions"', false);

        $this->post(route('school-settings.school.approve', $pending), ['redirect_to' => 'subscriptions'])->assertRedirect(route('subscriptions'));
        $this->assertSame('active', $pending->fresh()->status);
    }
}
