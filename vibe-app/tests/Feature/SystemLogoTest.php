<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The ProcureMS logo: the full logo on light pages, the round emblem on navy ones and as the browser-tab icon everywhere. */
class SystemLogoTest extends TestCase
{
    use RefreshDatabase;

    private const SIDEBAR_PAGES = ['home', 'budget', 'liquidation', 'accounting', 'reports', 'subscriptions', 'user-management', 'google-drive', 'drive-files.index', 'backup.index', 'procurement'];

    private const EMBLEM = 'images/procurems-emblem.svg';

    private const LOGO = 'images/procurems-logo.svg';

    /** Big enough that the emblem's roof, document and check mark can be made out on a sidebar (3.5rem, 56px). */
    private const EMBLEM_SIZE = 'style="width:3.5rem;height:3.5rem"';

    public function test_the_emblem_file_is_a_clean_transparent_svg_cut_from_the_logo(): void
    {
        $emblem = file_get_contents(public_path(self::EMBLEM));
        $logo = file_get_contents(public_path(self::LOGO));

        $this->assertStringContainsString('viewBox="21 20 210 210"', $emblem);
        $this->assertStringNotContainsString('<text', $emblem, 'the wordmark stays out of the emblem');
        $this->assertStringNotContainsString('<script', $emblem);
        $this->assertStringNotContainsString('fill="#ffffff"/>', explode('<circle', $emblem)[0], 'no white background plate');
        // Cut from the logo, not redrawn: the emblem's circle and check mark are the logo's own paths.
        foreach (['<circle cx="126" cy="125" r="105" fill="#00236f"/>', '<path d="m149 165 7 7 15-17"'] as $piece) {
            $this->assertStringContainsString($piece, $logo);
            $this->assertStringContainsString($piece, $emblem);
        }
    }

    public function test_the_login_page_shows_the_full_logo(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString(self::LOGO, $html);
        $this->assertMatchesRegularExpression('/<img[^>]*src="[^"]*'.preg_quote(self::LOGO, '/').'"[^>]*alt="ProcureMS[^"]*"/', $html);
    }

    public function test_the_register_page_shows_the_emblem_with_its_text(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee(self::EMBLEM, false)->assertSee(self::EMBLEM_SIZE, false)->assertSee('ProcureMS')->assertSee('School Pre-registration');
    }

    public function test_every_sidebar_page_shows_the_emblem_and_keeps_the_brand_text(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (self::SIDEBAR_PAGES as $page) {
            $html = $this->actingAs($master)->get(route($page))->assertOk()->getContent();

            $this->assertStringContainsString(self::EMBLEM, $html, $page);
            $this->assertStringContainsString('ProcureMS', $html, $page);
            $this->assertStringContainsString('Civic Operations', $html, $page);
            $this->assertStringContainsString(self::EMBLEM_SIZE, $html, $page.' emblem is too small to read');
            $this->assertStringNotContainsString('account_balance</span></div><div><p class="text-lg', $html, $page.' still has the old icon chip');
        }
    }

    public function test_every_normal_page_sets_the_emblem_as_the_tab_icon(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);
        $pages = collect(['login', 'register', 'privacy'])->map(fn ($page) => $this->get(route($page))->assertOk()->getContent());
        foreach (self::SIDEBAR_PAGES as $page) {
            $pages->push($this->actingAs($master)->get(route($page))->assertOk()->getContent());
        }

        foreach ($pages as $index => $html) {
            $this->assertMatchesRegularExpression('/<link[^>]*rel="icon"[^>]*href="[^"]*'.preg_quote(self::EMBLEM, '/').'"/', $html, 'page #'.$index);
            $this->assertStringContainsString('favicon.ico', $html, 'page #'.$index.' keeps the .ico fallback');
        }
    }
}
