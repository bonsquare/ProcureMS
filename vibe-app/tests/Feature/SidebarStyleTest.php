<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page uses the paler navy sidebar of the Procurement pages (civic navigation): same brand, footer, colour and width. */
class SidebarStyleTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['home', 'budget', 'liquidation', 'accounting', 'reports', 'subscriptions', 'user-management', 'google-drive', 'drive-files.index', 'backup.index'];

    /** Budget and Accounting have never had a footer in their sidebar. */
    private const PAGES_WITH_FOOTER = ['home', 'liquidation', 'reports', 'subscriptions', 'user-management', 'google-drive', 'drive-files.index', 'backup.index'];

    public function test_every_page_shows_the_same_sidebar_brand_and_footer(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (self::PAGES as $page) {
            $response = $this->actingAs($master)->get(route($page))->assertOk()->assertSee('Civic Operations')->assertDontSee('All systems operational');
            if (in_array($page, self::PAGES_WITH_FOOTER, true)) {
                $response->assertSee('Secure operational workspace');
            }
        }
    }

    public function test_the_sidebar_colour_and_width_are_the_procurement_ones_on_every_kind_of_page(): void
    {
        $tailwindPages = file_get_contents(public_path('css/app.css'));
        $procurementPages = file_get_contents(public_path('css/procurement.css'));

        $this->assertStringContainsString('--civic-navy:#103967', $procurementPages);
        $this->assertStringContainsString('aside.fixed.inset-y-0.w-72 { width: 15rem !important; background: #103967 !important; }', $tailwindPages);
        $this->assertStringContainsString('.md\:pl-72 { padding-left: 15rem !important; }', $tailwindPages);
        $this->assertStringContainsString('header.md\:left-72 { left: 15rem !important; }', $tailwindPages);
    }
}
