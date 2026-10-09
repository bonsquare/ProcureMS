<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SipImportTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'database/seed-data/sip-lubas-2026-2028.json';

    private function school(): School
    {
        $organization = Organization::create(['name' => 'lubas', 'slug' => 'lubas', 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => 'SCH-8922', 'name' => 'Lubas Elementary School', 'status' => 'active', 'region' => 'Region XII', 'division' => 'Schools Division Office of Cotabato']);
    }

    public function test_the_sip_2026_2028_of_lubas_is_entered_exactly_as_in_the_pdf(): void
    {
        $school = $this->school();
        User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'SCH-8922'])->assertSuccessful();

        $projects = SipProject::withoutGlobalScopes()->where('school_id', $school->id)->get();
        $activities = SipActivity::withoutGlobalScopes()->get();
        $this->assertCount(33, $projects);
        $this->assertCount(116, $activities);
        $this->assertSame([766000.0, 802000.0, 693000.0], [(float) $activities->sum('financial_year1'), (float) $activities->sum('financial_year2'), (float) $activities->sum('financial_year3')]);
        $this->assertEqualsCanonicalizing(['Access', 'Equity', 'Quality', 'Well-Being', 'Enabling Mechanism'], $projects->pluck('pillar')->unique()->values()->all());
        $this->assertSame([2026], $projects->pluck('school_year')->unique()->values()->all());
        $this->assertSame('2026-2028', $projects->first()->planning_period);
        $this->assertNotNull($projects->first()->master_transaction_id);

        // A program keeps its columns, and every activity its targets and source of fund.
        $lingkuri = $projects->firstWhere('project', 'LINGKURI KO BI!');
        $this->assertSame('KRA 2: Teaching and Learning Delivery', $lingkuri->kra);
        $this->assertSame('Instructional Support Facilities Management', $lingkuri->strategy);
        $this->assertSame(21000.0, (float) $lingkuri->estimated_budget);
        $purchase = $lingkuri->activities->firstWhere('activity', 'Purchase of Chairs');
        $this->assertSame([null, null, 1.0], [$purchase->physical_year1 === null ? null : (float) $purchase->physical_year1, $purchase->physical_year2 === null ? null : (float) $purchase->physical_year2, (float) $purchase->physical_year3]);
        $this->assertSame([0.0, 0.0, 20000.0], [(float) $purchase->financial_year1, (float) $purchase->financial_year2, (float) $purchase->financial_year3]);
        $this->assertSame('Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE', $purchase->source_of_fund);

        // The signatories of the plan are entered too.
        $plan = SipPlan::withoutGlobalScopes()->where('school_id', $school->id)->first();
        $this->assertSame('Chiqueto E. Domingo', $plan->prepared_by_name);
        $this->assertSame('Julie B. Lumogdang, EdD', $plan->recommended_by_name);
        $this->assertSame('Romelito G. Flores, CESO V', $plan->approved_by_name);
    }

    public function test_the_text_has_no_broken_characters(): void
    {
        $json = file_get_contents(base_path(self::FILE));

        $this->assertStringNotContainsString("\u{FFFD}", $json);
        $this->assertStringContainsString("Teacher\u{2019}s representat", $json);
    }

    public function test_entering_the_same_sip_twice_is_refused(): void
    {
        $this->school();
        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'SCH-8922'])->assertSuccessful();

        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'SCH-8922'])->assertFailed();
        $this->assertSame(33, SipProject::withoutGlobalScopes()->count());
    }

    public function test_an_unknown_school_is_refused(): void
    {
        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'NOPE'])->assertFailed();
        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
    }

    public function test_the_printed_sip_shows_the_programs_in_pillar_order(): void
    {
        $school = $this->school();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->artisan('sip:import', ['file' => base_path(self::FILE), 'school' => 'SCH-8922'])->assertSuccessful();

        $html = $this->actingAs($master)->get(route('planning.sip.print', ['school_id' => $school->id, 'start_year' => 2026]))->assertOk()->getContent();

        foreach (['SCHOOL IMPROVEMENT PLAN', 'Lubas Elementary School', 'Papel mo Kinabukasan Ko!', 'LingKuri', 'Renewal of Fidelity Bond', 'CHIQUETO E. DOMINGO'] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, $html);
        }
        $this->assertLessThan(strpos($html, 'Where Learning Blooms'), strpos($html, 'Papel mo Kinabukasan Ko!'), 'Access comes before Well-Being');
        $this->assertLessThan(strpos($html, 'Renewal of Fidelity Bond'), strpos($html, 'Where Learning Blooms'), 'Well-Being comes before Enabling Mechanism');
    }
}
