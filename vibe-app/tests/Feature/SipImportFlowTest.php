<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\Support\SipSheet;
use Tests\TestCase;

class SipImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'sipflow', string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);

        return [$school, $user];
    }

    private function upload(User $user, School $school, array $cells = [], string $name = 'sip.xlsx')
    {
        $path = SipSheet::book(SipSheet::cells($cells), ['I12:K12', 'L12:N12', 'B14:B17']);
        $file = new UploadedFile($path, $name, null, null, true);

        return $this->actingAs($user)->post(route('planning.sip.import.preview'), ['school_id' => $school->id, 'file' => $file]);
    }

    private function token($response): string
    {
        return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }

    /** The plan the preview page carries (what the browser edits and posts back). */
    private function state(string $html): array
    {
        preg_match('/<script type="application\/json" id="sip-plan">(.*?)<\/script>/s', $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '{}', ENT_QUOTES), true);
    }

    private function confirm(User $user, string $token, array $state, array $extra = [])
    {
        return $this->actingAs($user)->post(route('planning.sip.import.store', $token), ['payload' => json_encode(['signatories' => $state['signatories'], 'projects' => $state['projects']])] + $extra);
    }

    public function test_upload_shows_the_preview_and_writes_nothing(): void
    {
        [$school, $user] = $this->tenant();

        $response = $this->upload($user, $school)->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Program One')->assertSee('Program Two')->assertSee('Program Three');

        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
    }

    public function test_confirm_saves_the_edited_plan_and_audits_the_edit(): void
    {
        [$school, $user] = $this->tenant();
        $token = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());

        $state['projects'][0]['activities'][0]['activity'] = 'Edited activity text';
        $state['projects'][0]['activities'][0]['financial'][0] = 999;
        $state['signatories']['prepared_by_name'] = 'Edited Head';
        array_splice($state['projects'], 1, 1);

        $this->confirm($user, $token, $state)->assertRedirect()->assertSessionHas('success');

        $projects = SipProject::withoutGlobalScopes()->where('school_id', $school->id)->pluck('project')->all();
        $this->assertEqualsCanonicalizing(['Program One', 'Program Three'], $projects);
        $this->assertSame(999.0, (float) SipActivity::withoutGlobalScopes()->where('activity', 'Edited activity text')->value('financial_year1'));
        $this->assertSame('Edited Head', SipPlan::withoutGlobalScopes()->where('school_id', $school->id)->value('prepared_by_name'));
        $audit = AuditLog::where('action', 'sip_imported')->firstOrFail();
        $this->assertTrue($audit->metadata['edited']);
        $this->assertSame(2, $audit->metadata['programs']);
    }

    public function test_an_unedited_confirm_is_audited_as_not_edited(): void
    {
        [$school, $user] = $this->tenant();
        $token = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());

        $this->confirm($user, $token, $state)->assertSessionHas('success');

        $audit = AuditLog::where('action', 'sip_imported')->firstOrFail();
        $this->assertFalse($audit->metadata['edited']);
        $this->assertSame(3, $audit->metadata['programs']);
        $this->assertSame(5, $audit->metadata['activities']);
    }

    public function test_invalid_edits_are_refused_and_keep_the_edits(): void
    {
        [$school, $user] = $this->tenant();
        $token = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());

        $bad = $state;
        $bad['projects'][0]['project'] = '';
        $bad['projects'][0]['pillar'] = 'Banana';
        $bad['projects'][0]['activities'][0]['financial'][1] = -3;
        $bad['projects'][1]['activities'][0]['activity'] = 'My typed fix';
        $this->confirm($user, $token, $bad)->assertRedirect(route('planning.sip.import.show', $token));

        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
        $this->get(route('planning.sip.import.show', $token))->assertOk()->assertSee('My typed fix')->assertSee('program name is empty');

        foreach (['{not json', '[]', json_encode(['projects' => 'x'])] as $payload) {
            $this->actingAs($user)->post(route('planning.sip.import.store', $token), ['payload' => $payload])->assertSessionHasErrors();
        }
        $this->actingAs($user)->post(route('planning.sip.import.store', $token), ['payload' => str_repeat('x', 2000001)])->assertSessionHasErrors('payload');
        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
    }

    public function test_the_payload_cannot_change_school_year_or_period(): void
    {
        [$school, $user] = $this->tenant();
        [$otherSchool] = $this->tenant('othersip');
        $token = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());

        $payload = ['signatories' => $state['signatories'], 'projects' => $state['projects'], 'school_id' => $otherSchool->id, 'plan' => ['start_year' => 2031, 'planning_period' => '2031-2033']];
        $this->actingAs($user)->post(route('planning.sip.import.store', $token), ['payload' => json_encode($payload), 'school_id' => $otherSchool->id])->assertSessionHas('success');

        $this->assertSame(0, SipProject::withoutGlobalScopes()->where('school_id', $otherSchool->id)->count());
        $this->assertSame([2026], SipProject::withoutGlobalScopes()->where('school_id', $school->id)->pluck('school_year')->unique()->values()->all());
        $this->assertSame('2026-2028', SipProject::withoutGlobalScopes()->where('school_id', $school->id)->value('planning_period'));
    }

    public function test_errors_in_the_file_block_confirm_until_fixed(): void
    {
        [$school, $user] = $this->tenant();
        $token = $this->token($this->upload($user, $school, ['B18' => 'Banana']));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());
        $this->assertNotEmpty(array_filter($state['issues'], fn ($issue) => $issue['level'] === 'error'));

        $this->confirm($user, $token, $state)->assertRedirect(route('planning.sip.import.show', $token));
        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());

        $state['projects'][2]['pillar'] = 'Quality';
        $this->confirm($user, $token, $state)->assertSessionHas('success');
        $this->assertSame(3, SipProject::withoutGlobalScopes()->count());
    }

    public function test_a_second_import_of_the_same_period_is_refused_and_a_used_token_is_dead(): void
    {
        [$school, $user] = $this->tenant();
        $first = $this->token($this->upload($user, $school));
        $second = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $first))->getContent());

        $this->confirm($user, $first, $state)->assertSessionHas('success');
        $this->confirm($user, $first, $state)->assertSessionMissing('success');
        $this->confirm($user, $second, $state)->assertSessionMissing('success');

        $this->assertSame(3, SipProject::withoutGlobalScopes()->count());
        $this->get(route('planning.sip.import.show', $first))->assertRedirect();
    }

    public function test_the_token_is_bound_to_user_and_school_and_expires(): void
    {
        [$school, $user] = $this->tenant();
        $other = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $token = $this->token($this->upload($user, $school));

        $this->actingAs($other)->get(route('planning.sip.import.show', $token))->assertNotFound();

        Carbon::setTestNow(now()->addMinutes(31));
        $this->actingAs($user)->get(route('planning.sip.import.show', $token))->assertRedirect()->assertSessionHas('error');
        Carbon::setTestNow();
    }

    public function test_wrong_file_type_and_oversize_are_refused(): void
    {
        [$school, $user] = $this->tenant();

        $pdf = UploadedFile::fake()->create('sip.pdf', 10, 'application/pdf');
        $this->actingAs($user)->post(route('planning.sip.import.preview'), ['school_id' => $school->id, 'file' => $pdf])->assertSessionHasErrors('file');

        $big = UploadedFile::fake()->create('sip.xlsx', 6000);
        $this->post(route('planning.sip.import.preview'), ['school_id' => $school->id, 'file' => $big])->assertSessionHasErrors('file');

        $text = UploadedFile::fake()->createWithContent('sip.xlsx', 'not a workbook');
        $response = $this->post(route('planning.sip.import.preview'), ['school_id' => $school->id, 'file' => $text])->assertRedirect();
        $state = $this->state($this->get($response->headers->get('Location'))->getContent());
        $this->assertSame([], $state['projects']);
        $this->assertNotEmpty($state['issues']);
    }

    public function test_only_planning_managers_may_import_and_only_for_their_school(): void
    {
        [$school, $user] = $this->tenant();
        [$otherSchool] = $this->tenant('foreignsip');
        $viewer = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_staff']);

        $this->upload($viewer, $school)->assertForbidden();
        $this->upload($user, $otherSchool)->assertSessionHasErrors('school_id');
        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
    }

    public function test_a_closed_fiscal_year_refuses_the_import(): void
    {
        [$school, $user] = $this->tenant();
        FiscalYear::create(['organization_id' => $school->organization_id, 'year' => 2026, 'status' => 'closed']);
        $token = $this->token($this->upload($user, $school));
        $state = $this->state($this->get(route('planning.sip.import.show', $token))->getContent());

        $this->confirm($user, $token, $state)->assertSessionMissing('success');
        $this->assertSame(0, SipProject::withoutGlobalScopes()->count());
    }

    public function test_html_in_cells_is_escaped_in_the_preview(): void
    {
        [$school, $user] = $this->tenant();
        $response = $this->upload($user, $school, ['H14' => '<script>alert(1)</script>']);

        $html = $this->get($response->headers->get('Location'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('<script>alert(1)</script>', $this->state($html)['projects'][0]['activities'][0]['activity']);
    }

    public function test_an_imported_program_and_activity_can_be_edited_and_deleted_afterwards(): void
    {
        [$school, $user] = $this->tenant();
        $token = $this->token($this->upload($user, $school));
        $this->confirm($user, $token, $this->state($this->get(route('planning.sip.import.show', $token))->getContent()))->assertSessionHas('success');

        $project = SipProject::withoutGlobalScopes()->where('project', 'Program One')->firstOrFail();
        $activity = $project->activities()->firstOrFail();

        $this->put(route('planning.sip.update', $project), ['pillar' => 'Equity', 'kra' => 'KRA edited', 'project' => 'Program One edited', 'school_year' => 2026])->assertRedirect();
        $this->put(route('planning.sip.activities.update', $activity), ['activity' => 'Activity edited', 'physical_year1' => 1, 'financial_year1' => 10])->assertRedirect();
        $this->assertSame('Program One edited', $project->fresh()->project);
        $this->assertSame('Activity edited', $activity->fresh()->activity);

        $this->delete(route('planning.sip.activities.destroy', $activity))->assertRedirect();
        $this->delete(route('planning.sip.destroy', $project))->assertRedirect();
        $this->assertNull(SipProject::withoutGlobalScopes()->find($project->id));
    }

    public function test_the_sip_panel_offers_import_to_planning_managers_only(): void
    {
        [$school, $user] = $this->tenant();
        $viewer = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'school_staff']);

        $this->actingAs($user)->get(route('planning', ['school_id' => $school->id]))->assertOk()
            ->assertSee('Import from Excel')->assertSee(route('planning.sip.import.preview'), false)->assertSee('accept=".xlsx"', false);

        $page = $this->actingAs($viewer)->get(route('planning', ['school_id' => $school->id]));
        if ($page->getStatusCode() === 200) {
            $page->assertDontSee('Import from Excel');
        } else {
            $this->assertContains($page->getStatusCode(), [302, 403]);
        }
    }

    public function test_the_preview_page_carries_the_plan_the_issues_and_the_confirm_form(): void
    {
        [$school, $user] = $this->tenant();
        $response = $this->upload($user, $school, ['B18' => 'Banana']);
        $other = $this->get($response->headers->get('Location'))->assertOk();

        $other->assertSee('id="sip-plan"', false)->assertSee('name="payload"', false)->assertSee('name="_token"', false)
            ->assertSee('id="sip-import-confirm"', false)->assertSee('id="sip-programs"', false)->assertSee('id="sip-import-totals"', false)
            ->assertSee('Excel row 18')->assertSee('Banana');

        $named = $this->upload($user, $school, ['B10' => 'SOME OTHER SCHOOL']);
        $this->get($named->headers->get('Location'))->assertSee('SOME OTHER SCHOOL')->assertSee('you are importing into');
    }
}
