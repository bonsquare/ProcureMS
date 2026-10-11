<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The Planning page: the numbered four-step flow and the shared heading of every panel. */
class PlanningLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_four_numbered_steps_that_open_their_panels(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        foreach (['sip' => 'Step 1 · SIP', 'aip' => 'Step 2 · AIP', 'sob' => 'Step 3 · SOB', 'app' => 'Step 4 · APP'] as $tab => $label) {
            $this->assertStringContainsString('data-tab="'.$tab.'"', $html);
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('data-panel="'.$tab.'"', $html);
        }
    }

    public function test_every_panel_uses_the_shared_heading_with_its_summary_chips(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        foreach (['School Improvement Plan (SIP)', 'Annual Implementation Plan', 'School Operating Budget (SOB) · FY 2026', 'Annual Procurement Plan · FY 2026', 'APP-CSE (Procurement Service form)'] as $title) {
            $this->assertStringContainsString('<h2 class="text-base font-semibold tracking-tight">'.$title.'</h2>', $html);
        }
        $this->assertStringContainsString('1 plan for FY 2026', $html, 'the AIP heading carries its summary');
    }
}
