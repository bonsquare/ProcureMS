<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\BudgetAllocation;
use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\User;
use App\Services\SipAipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SipToAipTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'database/seed-data/sip-lubas-2026-2028.json';

    private function lubas(): array
    {
        $organization = Organization::create(['name' => 'lubas', 'slug' => 'lubas', 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH-8922', 'name' => 'Lubas Elementary School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'SCH-8922'])->assertSuccessful();

        return [$school, $master];
    }

    private function generate(School $school, int $yearNo)
    {
        return $this->post(route('planning.sip.generate-aip'), ['school_id' => $school->id, 'start_year' => 2026, 'year_no' => $yearNo]);
    }

    /** SIP activities that have a target in the given plan year (1 to 3). */
    private function sipActivitiesOf(int $yearNo)
    {
        return SipActivity::withoutGlobalScopes()->get()->filter(fn ($a) => (float) $a->{'financial_year'.$yearNo} > 0 || (float) $a->{'physical_year'.$yearNo} > 0);
    }

    public function test_each_plan_year_becomes_an_aip_with_that_years_targets(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master);

        foreach ([1 => [2026, 766000.0], 2 => [2027, 802000.0], 3 => [2028, 693000.0]] as $yearNo => [$fiscalYear, $total]) {
            $this->generate($school, $yearNo)->assertRedirect();

            $aip = Aip::withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->firstOrFail();
            $this->assertSame('draft', $aip->status);
            $this->assertSame([2026, $yearNo], [$aip->sip_start_year, $aip->sip_year_no]);
            $activities = AipActivity::withoutGlobalScopes()->where('aip_id', $aip->id)->get();
            $this->assertCount($this->sipActivitiesOf($yearNo)->count(), $activities, "year {$yearNo} activities");
            $this->assertEqualsWithDelta($total, $activities->sum(fn ($a) => $a->total), 0.001, "year {$yearNo} total");
            $this->assertNotNull($aip->master_transaction_id);
        }
    }

    public function test_a_program_without_a_target_in_a_year_is_left_out_of_that_years_aip(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master);

        $this->generate($school, 1);
        $this->generate($school, 3);

        $year1 = Aip::withoutGlobalScopes()->where('fiscal_year', 2026)->first();
        $year3 = Aip::withoutGlobalScopes()->where('fiscal_year', 2028)->first();
        $programs = fn (Aip $aip) => AipKra::withoutGlobalScopes()->where('aip_id', $aip->id)->pluck('program')->all();
        $this->assertNotContains('BATANG PROTEKTADO SA PAG-AARAL SIGURADO', $programs($year1), 'only has year 3 targets');
        $this->assertContains('BATANG PROTEKTADO SA PAG-AARAL SIGURADO', $programs($year3));
        $this->assertNotContains('Water is life', $programs($year3), 'only has year 1 targets');
        $this->assertContains('Water is life', $programs($year1));
    }

    public function test_a_program_becomes_a_kra_block_and_an_activity_keeps_its_details(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master)->post(route('planning.sip.generate-aip'), ['school_id' => $school->id, 'start_year' => 2026, 'year_no' => 1]);

        $aip = Aip::withoutGlobalScopes()->where('fiscal_year', 2026)->first();
        $kra = AipKra::withoutGlobalScopes()->where('aip_id', $aip->id)->where('program', 'Papel mo Kinabukasan Ko!')->first();
        $this->assertSame(['Access', 'KRA 3: Learner Formation and Development', 'Learner Support Management'], [$kra->pillar, $kra->kra, $kra->strategy]);
        $this->assertSame('Percentage of School-age Children in School - Net Enrollment Rate (NER) in Elementary and 6-Year Target', $kra->intermediate_outcome);
        $this->assertSame('Enhanced Governance structure to ensure efficient and supportive Education System', $kra->five_point_agenda);
        $this->assertSame(5, $kra->activities()->withoutGlobalScopes()->count());

        $kra3 = AipKra::withoutGlobalScopes()->where('aip_id', $aip->id)->where('program', 'AGAHAN MO! SAGOT KO!')->first();
        $holding = $kra3->activities()->withoutGlobalScopes()->where('activity', 'Holding a background investigation')->first();
        $this->assertSame(1, (int) $holding->physical_target);
        $this->assertSame([125.0, 125.0, 125.0, 125.0], [(float) $holding->q1_amount, (float) $holding->q2_amount, (float) $holding->q3_amount, (float) $holding->q4_amount]);
        $this->assertSame(['Class Advisers, School Head, Project Team, DORP Coordinator'], $holding->responsible_persons);
        $this->assertNull($holding->source_of_fund, 'the SIP source is a list of funds; the budget officer picks one');
        $this->assertStringContainsString('Provincial Government', implode(' ', $holding->remarks_list));
        $this->assertNull($holding->chart_of_account_id);
    }

    public function test_the_quarter_split_is_even_and_the_leftover_cent_goes_to_the_last_quarter(): void
    {
        $split = app(SipAipService::class)->splitQuarters(100.01);

        $this->assertSame([25.0, 25.0, 25.0, 25.01], $split);
        $this->assertEqualsWithDelta(100.01, array_sum($split), 0.0001);
        $this->assertSame([0.0, 0.0, 0.0, 0.0], app(SipAipService::class)->splitQuarters(0));
        $this->assertSame([5000.0, 5000.0, 5000.0, 5000.0], app(SipAipService::class)->splitQuarters(20000));
    }

    public function test_a_year_that_already_has_an_aip_is_not_generated_again(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master);
        $this->generate($school, 1)->assertRedirect();

        $this->generate($school, 1)->assertSessionHasErrors('aip');
        $this->assertSame(1, Aip::withoutGlobalScopes()->where('fiscal_year', 2026)->count());
    }

    public function test_only_a_budget_manager_can_generate_and_the_year_must_be_1_to_3(): void
    {
        [$school, $master] = $this->lubas();
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);

        $this->actingAs($viewer);
        $this->generate($school, 1)->assertForbidden();

        $this->actingAs($master);
        $this->generate($school, 4)->assertSessionHasErrors('year_no');
        $this->assertSame(0, Aip::withoutGlobalScopes()->count());
    }

    public function test_the_generated_aip_opens_and_can_be_approved_without_accounts_and_creates_no_allotments(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master);
        $this->generate($school, 1);
        $aip = Aip::withoutGlobalScopes()->first();

        $this->get(route('aip.show', $aip))->assertOk()->assertSee('Papel mo Kinabukasan Ko!');
        $this->post(route('aip.approve', $aip))->assertSessionHasNoErrors();
        $this->assertSame('approved', $aip->fresh()->status);
        $this->assertSame(0, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_the_planning_page_shows_the_three_years_and_where_each_aip_came_from(): void
    {
        [$school, $master] = $this->lubas();
        $this->actingAs($master);

        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()
            ->assertSee('Break into AIP')->assertSee('Create AIP FY 2026')->assertSee('Create AIP FY 2027')->assertSee('Create AIP FY 2028')
            ->assertSee('SOB template');

        $this->generate($school, 1);

        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()
            ->assertDontSee('Create AIP FY 2026')->assertSee('Create AIP FY 2027')->assertSee('from SIP 2026-2028 · Year 1');
    }
}
