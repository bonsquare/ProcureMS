<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PrintSettings;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The print pages and the shared print engine take the margins of the school's print settings. */
class PrintSettingsEngineTest extends TestCase
{
    use RefreshDatabase;

    private function sob(): array
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        return [$fx, $plan];
    }

    private function settingsOf(string $html): array
    {
        $this->assertMatchesRegularExpression('/<script type="application\/json" id="print-settings">(.*?)<\/script>/s', $html);
        preg_match('/<script type="application\/json" id="print-settings">(.*?)<\/script>/s', $html, $found);

        return json_decode($found[1], true);
    }

    public function test_a_print_page_carries_the_settings_of_the_school(): void
    {
        [$fx, $plan] = $this->sob();
        app(PrintSettings::class)->save($fx['school'], ['top' => 15, 'right' => 10, 'bottom' => 15, 'left' => 10], ['sob' => ['top' => 12, 'right' => 8, 'bottom' => 14, 'left' => 8]]);

        $settings = $this->settingsOf($this->get(route('planning.sob.print', $plan))->assertOk()->getContent());

        $this->assertSame(15.0, (float) $settings['default']['top']);
        $this->assertSame(14.0, (float) $settings['documents']['sob']['bottom']);
    }

    public function test_a_school_without_settings_still_renders_the_engine(): void
    {
        [, $plan] = $this->sob();

        $settings = $this->settingsOf($this->get(route('planning.sob.print', $plan))->assertOk()->getContent());

        $this->assertNull($settings['default']);
        $this->assertEmpty($settings['documents']);
    }

    public function test_a_master_user_without_a_school_still_prints(): void
    {
        [, $plan] = $this->sob();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $settings = $this->actingAs($master)->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        $this->assertNull($this->settingsOf($settings)['default']);
    }

    public function test_the_engine_uses_four_margins_and_the_floor_for_page_by_page_documents(): void
    {
        $engine = File::get(resource_path('views/partials/print-clean.blade.php'));

        $this->assertStringContainsString("margin: ' + mTop + 'mm ' + mRight + 'mm ' + mBottom + 'mm ' + mLeft + 'mm", $engine);
        $this->assertStringContainsString('Math.max(mTop, 13)', $engine);
        $this->assertStringContainsString('Math.max(mBottom, 13)', $engine);
        $this->assertStringContainsString('getElementById(\'print-settings\')', $engine);
    }

    public function test_the_alphalist_page_has_its_document_key(): void
    {
        $this->assertStringContainsString('data-doc="alphalist"', File::get(resource_path('views/alphalist-print.blade.php')));
    }

    public function test_the_report_pages_take_the_default_margin(): void
    {
        foreach (['allotment-registry', 'budget-item-show', 'budget-report', 'financial-reports', 'generated-report'] as $view) {
            $this->assertStringContainsString("@include('partials.print-margin')", File::get(resource_path("views/{$view}.blade.php")), $view);
        }
    }

    public function test_the_margin_partial_writes_the_default_only_when_there_is_one(): void
    {
        [$fx] = $this->sob();

        $this->assertStringNotContainsString('@page', view('partials.print-margin')->render());

        app(PrintSettings::class)->save($fx['school'], ['top' => 15, 'right' => 10, 'bottom' => 15, 'left' => 10], []);

        $this->assertStringContainsString('@page { margin: 15mm 10mm 15mm 10mm; }', view('partials.print-margin')->render());
    }

    public function test_the_page_footer_is_out_of_the_flow_and_the_page_has_a_fixed_height(): void
    {
        $pages = File::get(resource_path('views/partials/print-pages.blade.php'));

        $this->assertStringContainsString('height: var(--doc-page-h, auto) !important; min-height: 0 !important; overflow: visible; display: flow-root', $pages);
        $this->assertStringContainsString('.doc-footer { position: absolute;', $pages);
        $this->assertStringContainsString("* MM - 4) + 'px'", $pages);
    }

    public function test_the_engine_opens_a_document_on_the_paper_of_the_school(): void
    {
        [$fx, $plan] = $this->sob();
        app(PrintSettings::class)->savePaper($fx['school'], 'folio');

        $this->assertSame('folio', $this->settingsOf($this->get(route('planning.sob.print', $plan))->assertOk()->getContent())['paper']);
        $this->assertStringContainsString('settings.paper', File::get(resource_path('views/partials/print-clean.blade.php')));
    }

    public function test_the_paginator_lays_a_document_out_once_per_paper_and_only_after_the_page_has_loaded(): void
    {
        $pages = File::get(resource_path('views/partials/print-pages.blade.php'));

        $this->assertStringContainsString('if (key === laidOutFor', $pages);
        $this->assertStringContainsString("document.readyState !== 'complete'", $pages);
        $this->assertStringContainsString('document.fonts.ready.then(paginate)', $pages);
    }

    public function test_every_page_footer_has_the_same_rule_and_size_and_no_document_overrides_it(): void
    {
        $this->assertStringContainsString('border-top: .5pt solid #7b8794', File::get(resource_path('views/partials/print-pages.blade.php')));
        $this->assertStringContainsString('var(--doc-footer-size, 8pt)', File::get(resource_path('views/partials/print-pages.blade.php')));
        foreach (glob(resource_path('views/*.blade.php')) as $view) {
            $this->assertStringNotContainsString('--doc-footer-size:', File::get($view), basename($view));
        }
    }

    public function test_the_sip_text_is_9_5_pt_with_centred_figures_that_never_run_into_the_next_column(): void
    {
        $sip = File::get(resource_path('views/sip-print.blade.php'));

        $this->assertStringContainsString('pagesAt(9.5);', $sip);
        $this->assertStringContainsString('function fitNumbers()', $sip);
        $this->assertStringContainsString('td.num { white-space: nowrap; text-align: center;', $sip);
    }
}
