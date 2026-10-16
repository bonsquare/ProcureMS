<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The SOB follows the school's own workbook: pillar names and colors, green header, School ID, logos and a page footer. */
class SobPrintDesignTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    private function activityIn(array $fx, string $pillar, string $program): AipActivity
    {
        $kra = AipKra::create(['organization_id' => $fx['organization']->id, 'aip_id' => $fx['aip']->id, 'pillar' => $pillar, 'kra' => 'KRA', 'program' => $program]);

        return AipActivity::create(['organization_id' => $fx['organization']->id, 'aip_id' => $fx['aip']->id, 'aip_kra_id' => $kra->id, 'activity' => 'Activity of '.$program, 'physical_target' => 1, 'q1_amount' => 0, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0]);
    }

    private function line(array $fx, AipActivity $activity, string $particulars = 'Bond paper'): array
    {
        return ['aip_activity_id' => $activity->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => $particulars, 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100];
    }

    private function printed(SobPlan $plan): string
    {
        return $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();
    }

    public function test_resiliency_and_well_being_are_one_pillar_and_the_order_follows_the_workbook(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        foreach (['Enabling Mechanism', 'Well-Being', 'Equity', 'Resiliency', 'Quality', 'Access'] as $pillar) {
            $this->service()->addItem($plan, $this->line($fx, $this->activityIn($fx, $pillar, 'P '.$pillar)));
        }

        $grouped = collect($this->service()->grouped($plan));

        $this->assertSame(['Access', 'Quality', 'Equity', 'Resiliency and Well-Being', 'Governance'], $grouped->pluck('pillar')->all());
        $this->assertSame(2000.0, (float) $grouped->firstWhere('pillar', 'Resiliency and Well-Being')['total'], 'both programs sit under the one pillar');
    }

    public function test_the_print_shows_every_pillar_in_order_even_without_items_with_the_workbook_colors(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItem($plan, $this->line($fx, $fx['activities'][0]));

        $html = $this->printed($plan);

        preg_match_all('/<tr class="pillar-row pillar-([a-z-]+)"><td colspan="7">([^<]+)<\/td><\/tr>/', $html, $rows);
        $this->assertSame(['ACCESS', 'QUALITY', 'EQUITY', 'RESILIENCY &amp; WELL-BEING', 'GOVERNANCE'], $rows[2], 'all five pillars, items or not');
        foreach (['#ffff00' => 'access', '#2e75b5' => 'quality', '#f4af84' => 'equity', '#92d050' => 'resiliency', '#548235' => 'governance'] as $color => $pillar) {
            $this->assertMatchesRegularExpression('/pillar-'.$pillar.'[^}]*background:\s*'.$color.'/i', $html, "$pillar keeps its color");
        }
        $this->assertMatchesRegularExpression('/thead th\s*\{[^}]*background:\s*#00af50/i', $html, 'the header is green');
        $this->assertMatchesRegularExpression('/tr\.grand td\s*\{[^}]*background:\s*#92d050/i', $html, 'grand totals are light green');
    }

    public function test_the_summary_leaves_out_the_account_code(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItem($plan, $this->line($fx, $fx['activities'][0]));

        $html = $this->printed($plan);
        $summary = substr($html, strpos($html, 'Summary per object of expenditure'));

        $this->assertStringContainsString('Office Supplies Expenses', $summary);
        $this->assertStringNotContainsString('5-02-03-010', $summary);
    }

    public function test_the_school_id_is_typed_by_hand_bold_with_a_bar_line_under_it(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee('name="school_id_number"', false);
        $this->put(route('planning.sob.update', $plan), ['school_id_number' => '130059'])->assertSessionHasNoErrors();
        $this->assertSame('130059', $plan->fresh()->school_id_number);

        $html = $this->printed($plan);
        $this->assertStringContainsString('<div class="school-id">School ID: <strong>130059</strong></div>', $html);
        $this->assertMatchesRegularExpression('/\.school-id\s*\{[^}]*border-bottom:\s*2px solid/', $html, 'a bar line under the School ID');

        $this->put(route('planning.sob.update', $plan), ['school_id_number' => ''])->assertSessionHasNoErrors();
        $this->assertStringContainsString('School ID: <strong>'.$fx['school']->code.'</strong>', $this->printed($plan->fresh()), 'blank falls back to the school profile');
    }

    public function test_the_deped_school_id_of_the_school_profile_is_printed_when_the_sob_has_none(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $this->post(route('school-settings.school'), ['school_id' => $fx['school']->id, 'name' => $fx['school']->name, 'deped_school_id' => '130059'])->assertSessionHasNoErrors();

        $this->assertSame('130059', $fx['school']->fresh()->deped_school_id);
        $this->assertStringContainsString('School ID: <strong>130059</strong>', $this->printed($plan->fresh()));

        $this->put(route('planning.sob.update', $plan), ['school_id_number' => '999'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('School ID: <strong>999</strong>', $this->printed($plan->fresh()), 'the number typed on the SOB still wins');
    }

    public function test_the_agency_logo_is_on_the_left_the_school_logo_on_the_right_and_the_footer_has_title_and_page_numbers(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 3, 'MOOE', $fx['user']);

        $html = $this->printed($plan);

        $this->assertMatchesRegularExpression('/class="masthead-logo masthead-logo-left"/', $html);
        $this->assertMatchesRegularExpression('/class="masthead-logo masthead-logo-right"/', $html);
        $this->assertLessThan(strpos($html, 'Republic of the Philippines'), strpos($html, 'class="masthead-logo masthead-logo-left"'), 'left logo comes before the title block');
        $this->assertGreaterThan(strpos($html, 'Republic of the Philippines'), strpos($html, 'class="masthead-logo masthead-logo-right"'), 'right logo after it');
        $this->assertStringContainsString('data-page-footer="SOB 3rd Quarter 2026"', $html, 'the footer carries the document title on the right and the page number in the middle');
    }

    public function test_the_add_item_choices_are_the_specific_programs_of_the_aip(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $solo = $this->activityIn($fx, 'Access', 'Sustain Retention Rate (AGAHAN MO! SAGOT KO!)');
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="'.$solo->id.'"', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$solo->id.'"[^>]*>\s*Sustain Retention Rate \(AGAHAN MO! SAGOT KO!\)\s*<\/option>/', $html, 'the program name exactly');
        $this->assertMatchesRegularExpression('/<option value="'.$fx['activities'][0]->id.'"[^>]*>\s*Program One\s*<\/option>/', $html, 'the program alone, with no activity added');
    }

    public function test_a_program_with_several_activities_is_one_choice_without_its_activities(): void
    {
        $fx = SobFixture::make(['activityCount' => 3]);
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<option value="\d+"[^>]*>\s*Program One\s*<\/option>/', $html), 'the program once, not once for each activity');
        $this->assertMatchesRegularExpression('/<option value="'.$fx['activities'][0]->id.'"[^>]*>\s*Program One\s*<\/option>/', $html, 'kept under its first activity');
        $this->assertStringNotContainsString('Activity 1', $html);
        $this->assertStringNotContainsString('Activity 2', $html);
    }
}
