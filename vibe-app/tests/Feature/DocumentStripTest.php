<?php

namespace Tests\Feature;

use App\Models\AppCsePlan;
use App\Models\SobPlan;
use App\Services\AppCseService;
use App\Services\SobService;
use App\Support\DocumentStrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The strip on top of a planning document: the official form it makes, what it is based on, its status, what is missing and its history. */
class DocumentStripTest extends TestCase
{
    use RefreshDatabase;

    private function check(array $strip, string $label): array
    {
        return collect($strip['checks'])->firstWhere('label', $label) ?? $this->fail("No check named: $label");
    }

    public function test_the_aip_page_shows_its_official_form_status_and_what_is_missing(): void
    {
        $fx = SobFixture::make(['status' => 'draft']);
        $this->actingAs($fx['user']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        $this->assertStringContainsString('Makes the official form', $html);
        $this->assertStringContainsString('Official Annual Implementation Plan form', $html);
        $this->assertStringContainsString('Review before generating', $html);
        $this->assertStringContainsString('Draft', $html);
        $this->assertStringContainsString('Approve the AIP to start the SOB.', $html);

        $strip = DocumentStrip::aip($fx['aip']->fresh());
        $this->assertTrue($this->check($strip, 'At least one activity')['ok']);
        $this->assertFalse($this->check($strip, 'Every activity with an amount has a source of fund')['ok'], 'the fixture activities have no source of fund');
        $this->assertFalse($this->check($strip, 'Signatories are named')['ok']);
        $this->assertSame(0, $strip['missingRequired']);
    }

    public function test_a_required_check_is_missing_until_the_document_has_what_the_approval_needs(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $empty = DocumentStrip::sob($plan);
        $this->assertSame(2, $empty['missingRequired'], 'no item, so no item with an amount either... the two required checks');
        $this->assertSame('Add the items with their amounts.', $empty['next']);
        $this->assertSame('AIP FY 2026', $empty['source']['label'], 'based on the AIP of the year');

        $this->post(route('planning.sob.items.store', $plan), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100])->assertSessionHasNoErrors();
        $filled = DocumentStrip::sob($plan->fresh());
        $this->assertSame(0, $filled['missingRequired']);
        $this->assertStringContainsString('Approve the SOB', $filled['next']);

        $page = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        $this->assertStringContainsString('Official School Operating Budget form', $page);
        $this->assertStringContainsString('of 5 ready', $page);
    }

    public function test_the_history_lists_the_events_the_system_already_records(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->post(route('planning.sob.items.store', $plan), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100]);
        $this->post(route('planning.sob.approve', $plan))->assertSessionHasNoErrors();

        $strip = DocumentStrip::sob($plan->fresh());
        $what = $strip['events']->pluck('what')->all();

        $this->assertContains('Approved', $what);
        $this->assertContains('Draft Created', $what);
        $this->assertSame('Approved', $strip['events']->first()['what'], 'newest first');
        $this->assertStringContainsString('recorded events', $this->get(route('planning.sob.show', $plan))->assertOk()->getContent());
    }

    public function test_the_app_cse_and_the_planning_panels_show_their_strip(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $cse = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);

        $page = $this->get(route('planning.app-cse.show', $cse))->assertOk()->getContent();
        $this->assertStringContainsString('Official APP-CSE form of the Procurement Service', $page);
        $this->assertStringContainsString('A separate plan from the Procurement Service catalog', $page);
        $this->assertFalse($this->check(DocumentStrip::appCse($cse), 'At least one item with a quantity')['ok']);

        $planning = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $this->assertStringContainsString('Official School Improvement Plan form (three years)', $planning);
        $this->assertStringContainsString('Official Annual Procurement Plan form', $planning);
        $this->assertStringContainsString('Not generated yet', $planning);
        $this->assertStringContainsString('Approve a quarter of the SOB first.', $planning);
    }

    public function test_the_strip_changes_nothing_in_the_official_prints(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $sob = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $cse = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);

        foreach ([route('aip.print', $fx['aip']), route('planning.sob.print', $sob), route('planning.app-cse.print', $cse)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Makes the official form', $html, 'the strip is not in '.$url);
            $this->assertStringNotContainsString('Review before generating', $html);
        }
        $this->assertSame(1, SobPlan::count() + AppCsePlan::count() - 1, 'and it saved nothing');
    }
}
