<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The ProcMS logo (owner's new logo 2026-11-03): the full logo on a white plate on the menus, the login page and the registration page, and the emblem as the browser-tab icon everywhere. */
class SystemLogoTest extends TestCase
{
    use RefreshDatabase;

    private const SIDEBAR_PAGES = ['home', 'budget', 'liquidation', 'accounting', 'reports', 'subscriptions', 'user-management', 'google-drive', 'drive-files.index', 'backup.index', 'procurement'];

    private const EMBLEM = 'images/procurems-emblem.png';

    private const LOGO = 'images/procurems-logo.png';

    /** Big enough that the emblem's roof, document and check mark can be made out on a sidebar (3.5rem, 56px). */
    private const EMBLEM_SIZE = 'style="width:3.5rem;height:3.5rem"';

    public function test_the_emblem_and_logo_files_are_transparent_pngs_of_the_new_brand(): void
    {
        [$emblemWidth, $emblemHeight, $type] = getimagesize(public_path(self::EMBLEM));
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertSame($emblemWidth, $emblemHeight, 'the emblem is square');

        [$logoWidth, $logoHeight, $logoType] = getimagesize(public_path(self::LOGO));
        $this->assertSame(IMAGETYPE_PNG, $logoType);
        $this->assertGreaterThan($logoHeight * 2, $logoWidth, 'the full logo is wide: emblem, name and tagline');

        // Colour type 6 in the PNG header is RGBA: the picture has a transparent background, no plate.
        $this->assertSame(6, ord(file_get_contents(public_path(self::EMBLEM))[25]));
        $this->assertSame(6, ord(file_get_contents(public_path(self::LOGO))[25]));
    }

    /** The sign-in page was redesigned (specs section 36): the brand panel and the phone header carry the full ProcMS logo on a white plate. */
    public function test_the_login_page_shows_the_full_logo(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('images/procurems-logo-light.png', $html);
        $this->assertStringContainsString('ProcMS - Plan. Procure. Finance. Report.', $html);
    }

    public function test_the_register_page_shows_the_full_logo_with_its_text(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('images/procurems-logo-light.png', false)->assertSee('ProcMS - Plan. Procure. Finance. Report.', false)->assertSee('New User Registration');
    }

    public function test_every_sidebar_page_shows_the_full_logo_and_keeps_the_brand_text(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (self::SIDEBAR_PAGES as $page) {
            $html = $this->actingAs($master)->get(route($page))->assertOk()->getContent();

            $this->assertStringContainsString('images/procurems-logo-light.png', $html, $page);
            $this->assertStringContainsString('ProcMS - Plan. Procure. Finance. Report.', $html, $page);
            $this->assertStringNotContainsString('text-white/60">Civic Operations', $html, $page.' still has the caption under the logo');
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
