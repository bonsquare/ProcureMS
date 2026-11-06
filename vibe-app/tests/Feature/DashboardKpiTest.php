<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
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
            ->assertSee('KPI dashboard')->assertSee('Budget utilization')->assertSee('Pending approval')->assertSee('Cash balance')->assertSee('DVs for payment')->assertDontSee('Completion rate')->assertDontSee('Active suppliers')->assertDontSee('Requests per month')->assertDontSee('Request pipeline')->assertDontSee('Budget flow')->assertDontSee('Needs your attention')->assertDontSee('Recent requests')->assertDontSee('Budget by quarter')->assertSee('School Workspace')->assertSee('School Dashboard')->assertDontSee('My KPI Dashboard')
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
            ->assertSee('System Master Dashboard')->assertSee('System KPIs')->assertSee('Audit events')->assertDontSee('KPI Dashboard')->assertSee('Proofs to review')->assertSee('Ending within 30 days')->assertDontSee('Pending PRs')->assertDontSee('Branding Settings')->assertDontSee('Master Account')->assertSee('Total Schools')->assertDontSee('School performance')->assertDontSee('Budget flow');

        $system = $this->actingAs($master)->get(route('home'))->getContent();
        $this->assertLessThan(strpos($system, 'id="schools-section"'), strpos($system, 'aria-label="System KPIs"'), 'System KPIs sit above the school list');
        $this->assertGreaterThan(strpos($system, 'Total Schools'), strpos($system, 'aria-label="System KPIs"'));

        // The super admin has one dashboard: the old KPI view opens the same page.
        $response = $this->actingAs($master)->get(route('home', ['view' => 'kpi']))->assertOk()
            ->assertSee('Proofs to review')->assertDontSee('Planning flow')->assertSee('kpi-c School')->assertSee('kpi-d School');

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

    public function test_the_live_dashboard_answers_204_when_nothing_changed_and_redraws_when_it_did(): void
    {
        [$organization, $school, $user] = $this->school('live-a');
        [$otherOrganization, $otherSchool, $otherUser] = $this->school('live-b');
        $this->request($otherOrganization, $otherSchool, $otherUser, 'PR-OTHER-1', 'pending_approval', 5000);
        $page = $this->actingAs($user)->get(route('home', ['view' => 'kpi']))->assertOk()->assertSee('Day ', false)->assertSee('data-countup', false);
        $hash = $page->viewData('dashboardHash');

        $this->getJson(route('dashboard.live', ['h' => $hash]))->assertNoContent();

        $this->request($organization, $school, $user, 'PR-LIVE-1', 'pending_approval', 1000);
        $changed = $this->getJson(route('dashboard.live', ['h' => $hash]))->assertOk();
        $this->assertNotSame($hash, $changed->json('hash'));
        $this->assertStringContainsString('Purchase requests', $changed->json('html'));
        $this->assertStringNotContainsString('PR-OTHER-1', $changed->json('html'));
    }

    public function test_the_dashboard_shows_the_obligation_liquidation_and_disbursement_stages(): void
    {
        [, , $user] = $this->school('stage-a');

        $page = $this->actingAs($user)->get(route('home', ['view' => 'kpi']))->assertOk()
            ->assertSee('1 · Obligation', false)->assertSee('2 · Liquidation', false)->assertSee('3 · Disbursement', false);
        $cash = $page->viewData('kpi')['cash'];
        $this->assertSame(0, $cash['paid']);
        $this->assertSame(0.0, $cash['paid_amount']);
    }

    public function test_the_disbursement_is_the_dv_amount_less_the_tax_withheld_from_it(): void
    {
        [$organization, $school, $user] = $this->school('paid-a');
        $paid = fn (string $number, float $amount, array $deductions) => LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number, 'dv_number' => 'DV-'.$number,
            'amount' => $amount, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase', 'source_of_fund' => 'MOOE', 'dv_deductions' => $deductions,
            'payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-'.$number, 'paid_at' => now()->startOfYear()->addDays(10),
        ]);
        $paid('1', 89230, ['withholding_tax' => 4780.18]);
        $paid('2', 1000, []);

        $cash = $this->actingAs($user)->get(route('home', ['view' => 'kpi']))->assertOk()->viewData('kpi')['cash'];

        $this->assertSame(2, $cash['paid']);
        $this->assertSame(85449.82, $cash['paid_amount']);
    }

    public function test_the_dashboard_is_usable_with_a_keyboard_and_a_screen_reader(): void
    {
        [$organization, $school, $user] = $this->school('a11y-a');
        $this->request($organization, $school, $user, 'PR-A11Y-1', 'pending_approval', 1000);

        $html = $this->actingAs($user)->get(route('home', ['view' => 'kpi']))->assertOk()->getContent();

        // A skip link that lands on the main area.
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertMatchesRegularExpression('/<main id="main-content" tabindex="-1"/', $html);
        // Every progress bar has a name, so it is not read as a bare number.
        $this->assertGreaterThan(0, substr_count($html, 'role="progressbar"'));
        $this->assertSame(substr_count($html, 'role="progressbar"'), preg_match_all('/role="progressbar" aria-label="[^"]+"/', $html));
        // The sparkline lists its six monthly numbers, and the pulsing dot stops for people who reduce motion.
        $this->assertMatchesRegularExpression('/aria-label="Monthly requests, last six months: (\d+, ){5}\d+"/', $html);
        $this->assertStringContainsString('animate-ping motion-reduce:animate-none', $html);
        // The cards show where the keyboard is.
        $this->assertStringContainsString('focus-visible:outline-2', $html);
    }

    public function test_the_live_redraw_keeps_the_labels_and_the_script_keeps_the_keyboard_place(): void
    {
        [$organization, $school, $user] = $this->school('a11y-live');
        $page = $this->actingAs($user)->get(route('home', ['view' => 'kpi']))->assertOk();
        $this->request($organization, $school, $user, 'PR-A11Y-2', 'pending_approval', 1000);

        $live = $this->getJson(route('dashboard.live', ['h' => $page->viewData('dashboardHash')]))->assertOk()->json('html');

        $this->assertStringContainsString('role="progressbar" aria-label=', $live);
        // The redraw script remembers the focused card and the open meaning panel.
        $page->assertSee('const focusKey', false)->assertSee('openPanels', false)->assertSee('back.focus({ preventScroll: true })', false);
    }
}
