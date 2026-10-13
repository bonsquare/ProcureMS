<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AppCsePlan;
use App\Models\SipProject;
use App\Models\SobItem;
use App\Services\AppCseService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The official AIP and SOB are drawn beside the page; an entry that is not saved yet is shown, and nothing is saved. */
class PlanningPreviewDockTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_aip_and_sob_pages_carry_the_preview_dock_and_the_print_has_none(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $sob = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $this->get(route('aip.show', $fx['aip']))->assertOk()->assertSee('Show the official form')->assertSee('id="doc-dock"', false);
        $this->get(route('planning.sob.show', $sob))->assertOk()->assertSee('Show the official form')->assertSee(route('planning.sob.print', $sob), false);
        $this->get(route('aip.print', $fx['aip']))->assertOk()->assertDontSee('Show the official form');
    }

    public function test_the_aip_preview_shows_the_typed_amount_and_saves_nothing(): void
    {
        $fx = SobFixture::make(['activityCount' => 1, 'quarterAmount' => 125]);
        $this->actingAs($fx['user']);
        $activity = AipActivity::firstOrFail();
        $before = (float) $activity->q1_amount;

        $html = $this->post(route('aip.activities.budget.preview', [$fx['aip'], $activity]), ['q1_amount' => '98765.43', 'q2_amount' => '', 'q3_amount' => '', 'q4_amount' => '', 'timeline' => ''])->assertOk()->getContent();

        $this->assertStringContainsString('98,765.43', $html);
        $this->assertSame($before, (float) $activity->fresh()->q1_amount, 'a preview is rolled back');

        $this->post(route('aip.activities.budget.preview', [$fx['aip'], $activity]), ['q1_amount' => 'not a number'])->assertOk();
        $this->assertSame($before, (float) $activity->fresh()->q1_amount);
    }

    public function test_the_sob_preview_shows_an_item_that_is_not_added_yet_and_adds_nothing(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $sob = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->post(route('planning.sob.items.preview', $sob), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Preview Ballpen', 'frequency' => 1, 'quantity' => 4, 'unit' => 'box', 'unit_cost' => 250])->assertOk()->getContent();

        $this->assertStringContainsString('Preview Ballpen', $html);
        $this->assertSame(0, SobItem::count(), 'a preview is rolled back');

        $this->post(route('planning.sob.items.preview', $sob), ['particulars' => 'Incomplete'])->assertOk();
        $this->assertSame(0, SobItem::count());
        $this->assertSame(Aip::count(), 1);
    }

    public function test_the_sip_preview_draws_the_official_form_from_the_program_and_saves_nothing(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $html = $this->post(route('planning.sip.preview'), ['school_id' => $fx['school']->id, 'school_year' => 2026, 'pillar' => Aip::PILLARS[0], 'kra' => 'KRA Preview', 'project' => 'Preview Program Zeta'])->assertOk()->getContent();

        $this->assertStringContainsString('Preview Program Zeta', $html);
        $this->assertSame(0, SipProject::count(), 'a preview is rolled back');

        $this->post(route('planning.sip.preview'), ['school_id' => $fx['school']->id, 'school_year' => 2026, 'project' => 'Incomplete'])->assertOk();
        $this->assertSame(0, SipProject::count());

        $this->post(route('planning.sip.preview'), ['school_id' => 999999, 'school_year' => 2026])->assertForbidden();
    }

    public function test_the_app_cse_preview_shows_the_typed_details_and_the_page_has_the_dock(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $cse = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);

        $html = $this->post(route('planning.app-cse.preview', $cse), ['office_name' => 'Preview Office Omega'])->assertOk()->getContent();

        $this->assertStringContainsString('Preview Office Omega', $html);
        $this->assertNotSame('Preview Office Omega', AppCsePlan::firstOrFail()->office_name, 'a preview is rolled back');
        $this->get(route('planning.app-cse.show', $cse))->assertOk()->assertSee('Show the official form');
        $this->get(route('planning.app-cse.print', $cse))->assertOk()->assertDontSee('Show the official form');
    }
}
