<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sign-in page works with the keyboard, zoom and a screen reader (accessibility pass 2026-11-03, docs/specs.md section 118). */
class LoginAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_has_a_language_a_skip_link_one_heading_and_labelled_fields(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('href="#main"', $html);
        $this->assertStringContainsString('<main id="main" tabindex="-1"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'one h1 on the page');
        $this->assertStringNotContainsString('<h2', $html, 'the marketing line is not a heading that comes before the h1');
        $this->assertStringContainsString('<label for="email"', $html);
        $this->assertStringContainsString('<label for="password"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_the_text_size_follows_the_readers_setting_so_zoom_works(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('html{font-size:max(87.5%,min(100%,1.62vh))}', $html);
        $this->assertStringNotContainsString('clamp(9px', $html, 'no fixed pixel floor that ignores the reader');
    }

    public function test_a_clean_page_focuses_the_first_field_and_shows_no_error_summary(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee(' autofocus', false)->assertDontSee('id="error-summary"', false)->assertDontSee('aria-invalid="true" aria-describedby', false);
    }

    public function test_a_failed_sign_in_shows_a_linked_error_summary_and_marks_the_field(): void
    {
        User::factory()->create(['email' => 'real@example.test', 'password' => 'right-password-1']);

        $html = $this->followingRedirects()->from(route('login'))
            ->post(route('login.store'), ['email' => 'real@example.test', 'password' => 'wrong-password'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="error-summary" role="alert" tabindex="-1"', $html);
        $this->assertStringContainsString('<a href="#email"', $html, 'the summary links to the field');
        $this->assertStringContainsString('aria-invalid="true" aria-describedby="email-error"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('error-summary\')?.focus()', $html, 'focus moves to the summary');
        $this->assertStringNotContainsString(' autofocus', $html, 'no autofocus that would take focus away from the summary');
    }

    public function test_the_password_toggle_keeps_one_label_and_the_sign_in_is_announced(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Show password" aria-pressed="false"', $html);
        $this->assertStringNotContainsString("toggle.setAttribute('aria-label'", $html, 'the label does not change with the pressed state');
        $this->assertStringContainsString('id="signin-status" class="sr-only" role="status"', $html);
        $this->assertStringContainsString('Signing in, please wait', $html);
    }

    public function test_the_brand_text_keeps_the_contrast_classes_and_the_logo_has_a_text_name(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('text-white/50', $html);
        $this->assertStringNotContainsString('text-white/75', $html);
        $this->assertStringContainsString('alt="ProcMS - Plan. Procure. Finance. Report."', $html);
    }
}
