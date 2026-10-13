<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The Planning screens say where the user is, what each step needs next, and let the SIP programs be found and added without a long page. */
class PlanningWorkflowClarityTest extends TestCase
{
    use RefreshDatabase;

    private function program(array $fx, string $pillar, string $project): void
    {
        $this->post(route('planning.sip.store'), [
            'school_id' => $fx['school']->id, 'school_year' => 2026, 'pillar' => $pillar, 'kra' => 'KRA 3', 'organizational_outcome' => 'Outcome',
            'strategy' => 'Strategy', 'five_point_agenda' => 'Agenda', 'project' => $project,
        ])->assertSessionHasNoErrors();
    }

    public function test_the_aip_page_belongs_to_planning_and_does_not_ask_for_an_account_code_to_approve(): void
    {
        $fx = SobFixture::make(['status' => 'draft']);
        $this->actingAs($fx['user']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        $this->assertStringContainsString('>Planning</p><p class="truncate text-sm font-semibold text-on-surface">AIP</p>', $html, 'the top bar says Planning, not Reports');
        $this->assertStringContainsString(e(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026])).'#aip', $html, 'a way back to the Planning page');
        $this->assertStringNotContainsString('needed before approval', $html, 'approving the AIP needs no account code');
        $this->assertStringContainsString('not set (optional)', $html);
    }

    public function test_the_sip_counts_resiliency_and_well_being_as_one_pillar_and_can_be_filtered(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->program($fx, 'Resiliency', 'Program R');
        $this->program($fx, 'Well-Being', 'Program W');
        $this->program($fx, 'Access', 'Program A');

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Resiliency and Well-Being<span class="rounded-full bg-white\/70 px-1.5 text-\[11px\]">2<\/span>/', $html, 'one chip for the two names');
        $this->assertStringContainsString('<option value="Resiliency and Well-Being">Resiliency and Well-Being</option>', $html, 'the pillar filter');
        $this->assertSame(2, substr_count($html, 'data-pillar="Resiliency and Well-Being"'));
        $this->assertStringContainsString('data-sip-search', $html);
        $this->assertStringContainsString('data-search="program a kra 3 strategy outcome agenda"', $html, 'a program is found by its words');
    }

    public function test_adding_a_sip_program_or_activity_opens_a_window_instead_of_a_form_in_the_page(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->program($fx, 'Access', 'Program A');

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString('<dialog id="dlg-sip-new"', $html);
        $this->assertStringContainsString('<dialog id="dlg-activity-new"', $html);
        $this->assertStringContainsString('data-edit-dialog="dlg-sip-new" data-action="'.route('planning.sip.store').'"', $html);
        $this->assertStringNotContainsString('data-form="sip"', $html, 'the unfolding Add Program form is gone');
        $this->assertStringNotContainsString('data-form="sip-act-', $html, 'and the unfolding Add Activity forms');
    }

    public function test_each_step_card_says_what_it_needs(): void
    {
        $fx = SobFixture::make(['status' => 'draft']);
        $this->actingAs($fx['user']);
        $this->program($fx, 'Access', 'Program A');

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString('Next: Approve the AIP for FY 2026', $html, 'the AIP is drafted, so its card asks for the approval');
        $this->assertStringContainsString('Waits for: Approve the AIP for FY 2026', $html, 'the SOB waits for the approved AIP');
    }
}
