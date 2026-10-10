<?php

namespace Tests\Feature;

use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** Every dropdown becomes a type-to-search combo through one shared script. The behavior itself is checked in the browser. */
class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'data-searchable-select-script';

    public function test_both_layouts_load_the_enhancer_once(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        // layouts.budget
        $planning = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->assertSee(self::MARKER, false);
        $this->assertSame(1, substr_count($planning->getContent(), self::MARKER));

        // layouts.procurement
        $suppliers = $this->get(route('suppliers'))->assertOk()->assertSee(self::MARKER, false);
        $this->assertSame(1, substr_count($suppliers->getContent(), self::MARKER));
    }

    public function test_pages_that_include_input_fixes_directly_get_it_once(): void
    {
        $page = $this->get(route('register'))->assertOk()->assertSee(self::MARKER, false);

        $this->assertSame(1, substr_count($page->getContent(), self::MARKER));
    }

    public function test_the_script_excludes_the_toolbar_multi_selects_and_data_native(): void
    {
        $page = $this->get(route('register'))->assertOk();

        $this->assertSame(1, substr_count($page->getContent(), 'select[multiple], select[data-native], .official-toolbar select'));
        $page->assertSee('window.searchableSelect', false);
    }

    public function test_the_print_preview_toolbar_dropdowns_stay_plain_selects(): void
    {
        $fx = SobFixture::make();
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->actingAs($fx['user']);

        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        // The toolbar still renders real selects, and the enhancer's exclusion rule covers them.
        $this->assertStringContainsString('class="official-toolbar no-print"', $html);
        $this->assertSame(1, substr_count($html, '.official-toolbar select'));
        $this->assertGreaterThanOrEqual(3, substr_count($html, '<select'));
    }
}
