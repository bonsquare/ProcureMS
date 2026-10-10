<?php

namespace Tests\Feature;

use Tests\TestCase;

/** A long menu must scroll inside the sidebar instead of pushing the footer and the last items off the screen. */
class SidebarScrollTest extends TestCase
{
    public function test_both_sidebars_scroll_their_menu_and_keep_the_footer(): void
    {
        $tailwindPages = file_get_contents(public_path('css/app.css'));
        $procurementPages = file_get_contents(public_path('css/procurement.css'));

        $this->assertStringContainsString('aside.fixed.inset-y-0 > nav { min-height: 0; overflow-y: auto;', $tailwindPages);
        $this->assertStringContainsString('aside.fixed.inset-y-0 > :not(nav) { flex-shrink: 0; }', $tailwindPages);
        $this->assertStringContainsString('.civic-nav > nav { min-height: 0; overflow-y: auto;', $procurementPages);
        $this->assertStringContainsString('.civic-nav > :not(nav) { flex-shrink: 0; }', $procurementPages);
    }
}
