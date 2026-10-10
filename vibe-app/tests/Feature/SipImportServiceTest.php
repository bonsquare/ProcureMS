<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Services\SipImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SipImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        $organization = Organization::create(['name' => 'sipsvc', 'slug' => 'sipsvc', 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => 'SIPSVC', 'name' => 'Service School', 'status' => 'active']);
    }

    private function plan(?array $projects = null): array
    {
        return [
            'plan' => ['start_year' => 2026, 'planning_period' => '2026-2028', 'school_name' => 'Service School'],
            'signatories' => ['prepared_by_name' => 'Ana Head', 'prepared_by_position' => 'School Head', 'recommended_by_name' => 'Ben Chief', 'recommended_by_position' => 'Chief', 'approved_by_name' => 'Cy Super', 'approved_by_position' => 'Superintendent'],
            'projects' => $projects ?? [[
                'row' => 14, 'pillar' => 'Access', 'kra' => 'KRA 3', 'organizational_outcome' => 'Outcome', 'strategy' => 'Strategy', 'five_point_agenda' => 'Agenda', 'project' => 'Program One', 'source_of_fund' => 'MOOE',
                'activities' => [
                    ['row' => 14, 'activity' => 'First', 'physical' => [1, null, 2], 'financial' => [500, 0, 250.5], 'responsible_person' => 'Head', 'remarks' => null],
                    ['row' => 15, 'activity' => 'Second', 'physical' => [1, 1, 1], 'financial' => [100, 100, 100], 'responsible_person' => null, 'remarks' => 'note'],
                ],
            ]],
        ];
    }

    private function service(): SipImportService
    {
        return app(SipImportService::class);
    }

    public function test_validate_accepts_the_lubas_json(): void
    {
        $data = json_decode(file_get_contents(base_path('database/seed-data/sip-lubas-2026-2028.json')), true);

        $errors = collect($this->service()->validate($data))->where('level', 'error');
        $this->assertSame([], $errors->values()->all());
    }

    public function test_validate_reports_each_problem_with_its_row(): void
    {
        $bad = $this->plan([
            ['row' => 20, 'pillar' => 'Banana', 'kra' => '', 'project' => 'P', 'source_of_fund' => null, 'activities' => [
                ['row' => 21, 'activity' => '', 'physical' => [1, 1, 1], 'financial' => [-5, 0, 0], 'responsible_person' => null, 'remarks' => null],
            ]],
            ['row' => 30, 'pillar' => 'Access', 'kra' => 'KRA', 'project' => 'Empty program', 'source_of_fund' => null, 'activities' => []],
        ]);

        $issues = collect($this->service()->validate($bad));
        $errors = $issues->where('level', 'error');
        $this->assertTrue($errors->contains(fn ($i) => $i['row'] === 20 && str_contains($i['message'], 'pillar')));
        $this->assertTrue($errors->contains(fn ($i) => $i['row'] === 20 && str_contains($i['message'], 'KRA')));
        $this->assertTrue($errors->contains(fn ($i) => $i['row'] === 21 && str_contains($i['message'], 'Activity')));
        $this->assertTrue($errors->contains(fn ($i) => $i['row'] === 21 && str_contains($i['message'], 'financial')));
        $this->assertTrue($issues->contains(fn ($i) => $i['level'] === 'warning' && $i['row'] === 30));
    }

    public function test_validate_refuses_too_many_activities_and_missing_period(): void
    {
        $activities = array_fill(0, 5001, ['activity' => 'x', 'physical' => [1, 1, 1], 'financial' => [1, 1, 1], 'responsible_person' => null, 'remarks' => null]);
        $plan = $this->plan([['pillar' => 'Access', 'kra' => 'K', 'project' => 'P', 'activities' => $activities]]);
        $plan['plan']['planning_period'] = '';

        $messages = collect($this->service()->validate($plan))->where('level', 'error')->pluck('message')->implode(' | ');
        $this->assertStringContainsString('5,000', $messages);
        $this->assertStringContainsString('period', $messages);
    }

    public function test_save_writes_programs_activities_signatories_and_a_master_transaction(): void
    {
        $school = $this->school();

        $result = $this->service()->save($school, $this->plan(), null);

        $this->assertSame(['programs' => 1, 'activities' => 2], $result);
        $project = SipProject::withoutGlobalScopes()->where('school_id', $school->id)->firstOrFail();
        $this->assertSame(2026, (int) $project->school_year);
        $this->assertSame('2026-2028', $project->planning_period);
        $this->assertEquals(1050.5, (float) $project->estimated_budget);
        $this->assertNotNull($project->master_transaction_id);
        $first = SipActivity::withoutGlobalScopes()->where('activity', 'First')->firstOrFail();
        $this->assertNull($first->physical_year2);
        $this->assertSame('MOOE', $first->source_of_fund);
        $this->assertSame('Ana Head', SipPlan::withoutGlobalScopes()->where('school_id', $school->id)->value('prepared_by_name'));
    }

    public function test_save_refuses_the_same_period_twice_and_changes_nothing(): void
    {
        $school = $this->school();
        $this->service()->save($school, $this->plan(), null);
        $this->assertTrue($this->service()->alreadyImported($school, 2026, '2026-2028'));

        try {
            $this->service()->save($school, $this->plan(), null);
            $this->fail('Expected the duplicate to be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('plan', $e->errors());
        }
        $this->assertSame(1, SipProject::withoutGlobalScopes()->count());
        $this->assertSame(2, SipActivity::withoutGlobalScopes()->count());
    }

    public function test_save_is_all_or_nothing(): void
    {
        $school = $this->school();
        $plan = $this->plan();
        $plan['projects'][0]['activities'][1]['activity'] = str_repeat('x', 1500);

        try {
            $this->service()->save($school, $plan, null);
            $this->fail('Expected the oversized activity to stop the save.');
        } catch (\Throwable $e) {
            $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
            $this->assertSame(0, SipActivity::withoutGlobalScopes()->count());
        }
    }
}
