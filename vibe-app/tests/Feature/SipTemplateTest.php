<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SipTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        auth()->logout();
        $organization = Organization::create(['name' => strtoupper($slug), 'slug' => $slug]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => "School $slug", 'school_head' => 'Chiqueto E. Domingo']);
        $user = User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    private function program(School $school, array $overrides = []): array
    {
        return $overrides + [
            'school_id' => $school->id, 'school_year' => 2026, 'pillar' => 'Access', 'kra' => 'KRA 3: Learner Formation and Development',
            'organizational_outcome' => 'Percentage of School-age Children in School', 'strategy' => 'Learner Support Management',
            'five_point_agenda' => 'Enhanced Governance structure', 'project' => 'Papel mo Kinabukasan Ko!',
        ];
    }

    public function test_program_and_activities_follow_the_official_sip_columns(): void
    {
        [, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);

        $this->post(route('planning.sip.store'), $this->program($school))->assertSessionHasNoErrors();
        $sip = SipProject::firstOrFail();
        $this->assertSame('Access', $sip->pillar);
        $this->assertNotNull($sip->master_transaction_id);

        $this->post(route('planning.sip.activities.store', $sip), [
            'activity' => 'Listing of pupils who are transferred', 'physical_year1' => 1, 'physical_year2' => 1, 'physical_year3' => 1,
            'financial_year1' => 500, 'financial_year2' => 500, 'financial_year3' => 500, 'source_of_fund' => 'MOOE', 'responsible_person' => 'School Head',
        ])->assertSessionHasNoErrors();
        $this->post(route('planning.sip.activities.store', $sip), ['activity' => 'Crafting of Barangay Resolution', 'financial_year1' => 1000])->assertSessionHasNoErrors();

        $this->assertSame(2, $sip->activities()->count());
        $this->assertEquals(2500.0, (float) $sip->fresh()->estimated_budget);

        $activity = SipActivity::where('activity', 'Crafting of Barangay Resolution')->firstOrFail();
        $this->delete(route('planning.sip.activities.destroy', $activity))->assertSessionHasNoErrors();
        $this->assertEquals(1500.0, (float) $sip->fresh()->estimated_budget);
    }

    public function test_print_page_uses_the_official_layout_and_signatories(): void
    {
        [, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);
        $this->post(route('planning.sip.store'), $this->program($school));
        $sip = SipProject::firstOrFail();
        $this->post(route('planning.sip.activities.store', $sip), ['activity' => 'Conduct home visit', 'financial_year1' => 500, 'physical_year1' => 1, 'source_of_fund' => 'MOOE']);
        $this->post(route('planning.sip.signatories'), [
            'school_id' => $school->id, 'start_year' => 2026, 'recommended_by_name' => 'Julie B. Lumogdang, EdD', 'approved_by_name' => 'Romelito G. Flores, CESO V',
        ])->assertSessionHasNoErrors();

        $this->get(route('planning.sip.print', ['school_id' => $school->id, 'start_year' => 2026]))->assertOk()
            ->assertSee('SCHOOL IMPROVEMENT PLAN')->assertSee('FY 2026-2028')->assertSee("School alpha")
            ->assertSee('DepEd Organizational Outcomes')->assertSee('Strategy (Processes)')->assertSee('5-Point Agenda')
            ->assertSee('Physical Targets')->assertSee('Financial Target')->assertSee('YEAR 3')
            ->assertSee('Papel mo Kinabukasan Ko!')->assertSee('Conduct home visit')
            ->assertSee('Prepared by:')->assertSee('Recommending Approval:')->assertSee('Approved by:')
            ->assertSee('Chiqueto E. Domingo')->assertSee('Julie B. Lumogdang, EdD')->assertSee('Schools Division Superintendent');
        $this->assertSame(1, SipPlan::count());
    }

    public function test_sip_requires_permission_open_fiscal_year_and_isolation(): void
    {
        [$orgA, $schoolA, $userA] = $this->tenant('alpha');
        $this->actingAs($userA);
        $this->post(route('planning.sip.store'), $this->program($schoolA));
        $sipA = SipProject::firstOrFail();

        [, $schoolB, $userB] = $this->tenant('bravo');
        $this->actingAs($userB);
        $this->post(route('planning.sip.activities.store', $sipA), ['activity' => 'Intrusion'])->assertNotFound();
        $this->get(route('planning.sip.print', ['school_id' => $schoolA->id, 'start_year' => 2026]))->assertSessionHasErrors('school_id');
        $this->assertSame(0, SipActivity::withoutGlobalScopes()->count());

        [, $schoolC, $head] = $this->tenant('charlie', 'school_head');
        $this->actingAs($head);
        $this->post(route('planning.sip.store'), $this->program($schoolC))->assertForbidden();

        $this->actingAs($userA);
        app(FiscalYearService::class)->setStatus($orgA->id, 2026, 'closed', $userA->id);
        $this->post(route('planning.sip.activities.store', $sipA), ['activity' => 'Late addition'])->assertSessionHasErrors('fiscal_year');
        $this->assertSame(0, SipActivity::withoutGlobalScopes()->count());
    }

    public function test_planning_page_lists_sip_programs_with_print_button(): void
    {
        [, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);
        $this->post(route('planning.sip.store'), $this->program($school));

        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()
            ->assertSee('SIP FY 2026-2028')->assertSee('Print SIP')->assertSee('+ Add Activity')->assertSee('Papel mo Kinabukasan Ko!');
    }
}
