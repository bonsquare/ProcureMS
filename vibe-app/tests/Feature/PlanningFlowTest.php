<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\AppPlan;
use App\Models\ChartOfAccount;
use App\Models\Organization;
use App\Models\School;
use App\Models\SipProject;
use App\Models\SobPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => strtoupper($slug), 'slug' => $slug]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => "School $slug"]);
        $user = User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    public function test_sip_to_app_happy_path_keeps_master_transaction_links(): void
    {
        [$organization, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);

        $this->post(route('planning.sip.store'), [
            'school_id' => $school->id, 'school_year' => 2026, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'project' => 'Reading Program',
        ])->assertSessionHasNoErrors();
        $sip = SipProject::firstOrFail();
        $this->assertNotNull($sip->master_transaction_id);

        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'status' => 'approved']);
        $this->post(route('planning.sip.link-aip', $sip), ['aip_id' => $aip->id])->assertSessionHasNoErrors();
        $this->assertSame($sip->id, $aip->fresh()->sip_project_id);
        $this->assertNotNull($aip->fresh()->master_transaction_id);

        $kra = AipKra::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'program' => 'Reading Program']);
        $activity = AipActivity::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Buy reading materials', 'physical_target' => 1, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0]);
        $account = ChartOfAccount::create(['organization_id' => $organization->id, 'code' => '5-02-03-010', 'title' => 'Office Supplies Expenses', 'category' => 'Expense']);

        $this->post(route('planning.sob.store'), ['school_id' => $school->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE'])->assertSessionHasNoErrors();
        $sob = SobPlan::firstOrFail();
        $this->assertSame($aip->fresh()->master_transaction_id, $sob->master_transaction_id);
        $this->post(route('planning.sob.items.store', $sob), ['aip_activity_id' => $activity->id, 'chart_of_account_id' => $account->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 250])->assertSessionHasNoErrors();
        $this->assertEquals(2500, $sob->items()->first()->amount);

        $this->post(route('planning.app.generate'), ['school_id' => $school->id, 'fiscal_year' => 2026])->assertSessionHasErrors('planning');
        $this->assertSame(0, AppPlan::count());

        $this->post(route('planning.sob.approve', $sob))->assertSessionHasNoErrors();
        $this->post(route('planning.app.generate'), ['school_id' => $school->id, 'fiscal_year' => 2026])->assertSessionHasNoErrors();
        $app = AppPlan::firstOrFail();
        $this->assertSame(1, $app->items()->count());
        $this->assertSame($organization->id, $app->organization_id);

        // Regenerating must not duplicate items.
        $this->post(route('planning.app.generate'), ['school_id' => $school->id, 'fiscal_year' => 2026]);
        $this->assertSame(1, $app->items()->count());

        $this->post(route('planning.app.approve', $app))->assertSessionHasNoErrors();
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_closed_fiscal_year_blocks_planning_writes(): void
    {
        [$organization, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);
        app(FiscalYearService::class)->setStatus($organization->id, 2026, 'closed', $user->id);

        $this->post(route('planning.sip.store'), [
            'school_id' => $school->id, 'school_year' => 2026, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'project' => 'Blocked',
        ])->assertSessionHasErrors('fiscal_year');
        $this->assertSame(0, SipProject::count());
    }

    public function test_users_without_planning_manage_cannot_write(): void
    {
        [$organization, $school, $user] = $this->tenant('alpha', 'school_head');
        $this->actingAs($user);

        $this->post(route('planning.sip.store'), [
            'school_id' => $school->id, 'school_year' => 2026, 'pillar' => 'Quality', 'kra' => 'KRA 1', 'project' => 'Nope',
        ])->assertForbidden();
    }

    public function test_other_organizations_planning_records_are_not_reachable(): void
    {
        [$orgA, $schoolA, $userA] = $this->tenant('alpha');
        [$orgB, $schoolB, $userB] = $this->tenant('bravo');
        $aipB = Aip::create(['organization_id' => $orgB->id, 'school_id' => $schoolB->id, 'fiscal_year' => 2026, 'status' => 'approved']);
        $this->actingAs($userB);
        $this->post(route('planning.sob.store'), ['school_id' => $schoolB->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE'])->assertSessionHasNoErrors();
        $sobB = SobPlan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($userA);
        $this->post(route('planning.sob.store'), ['school_id' => $schoolB->id, 'fiscal_year' => 2026, 'quarter' => 2, 'fund_source' => 'MOOE'])->assertSessionHasErrors('school_id');
        $this->post(route('planning.sob.approve', $sobB))->assertNotFound();
        $this->post(route('planning.app.generate'), ['school_id' => $schoolB->id, 'fiscal_year' => 2026])->assertSessionHasErrors('school_id');
        $this->assertSame('draft', $sobB->fresh()->status);
        $this->assertSame(1, SobPlan::withoutGlobalScopes()->count());
    }

    public function test_aip_list_lives_on_the_planning_page(): void
    {
        [$organization, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);
        Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'status' => 'approved']);

        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))
            ->assertOk()->assertSee('Annual Implementation Plan')->assertSee('FY 2026')->assertSee('+ New AIP');
        $this->get(route('aip'))->assertRedirect(route('planning').'#aip');
    }

    public function test_planning_link_is_in_the_sidebar_of_every_main_page(): void
    {
        [, , $user] = $this->tenant('alpha');
        $this->actingAs($user);

        foreach (['home', 'procurement', 'liquidation', 'budget', 'accounting', 'reports', 'google-drive', 'school-settings', 'planning'] as $page) {
            $this->followingRedirects()->get(route($page))->assertOk()->assertSee('href="'.route('planning').'"', false);
        }
    }

    public function test_aip_page_explains_the_governing_entity(): void
    {
        [$organization, $school, $user] = $this->tenant('alpha');
        $this->actingAs($user);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026]);

        $this->get(route('aip.show', $aip))->assertOk()
            ->assertSee('What is the Governing Entity?')
            ->assertSee('Schools Division Office itself')
            ->assertSee('MOOE-GASS');
    }
}
