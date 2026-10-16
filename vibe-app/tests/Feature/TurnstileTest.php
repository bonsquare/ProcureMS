<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function keys(): void
    {
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    }

    public function test_without_keys_the_forms_show_no_check_and_sign_in_works_as_before(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('cf-turnstile', false);
        $this->get(route('register'))->assertOk()->assertDontSee('cf-turnstile', false);
        User::factory()->create(['username' => 'ts.user', 'password' => 'secret-pass-1', 'status' => 'active', 'role' => 'master_user', 'school_id' => null, 'organization_id' => null]);

        $this->post(route('login.store'), ['email' => 'ts.user', 'password' => 'secret-pass-1'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_with_keys_the_sign_in_and_registration_forms_show_the_check(): void
    {
        $this->keys();

        $this->get(route('login'))->assertOk()->assertSee('class="cf-turnstile"', false)->assertSee('data-sitekey="site-key"', false);
        $this->get(route('register'))->assertOk()->assertSee('class="cf-turnstile"', false);
    }

    public function test_with_keys_sign_in_needs_the_check_to_pass(): void
    {
        $this->keys();
        User::factory()->create(['username' => 'ts.user', 'password' => 'secret-pass-1', 'status' => 'active', 'role' => 'master_user', 'school_id' => null, 'organization_id' => null]);
        $credentials = ['email' => 'ts.user', 'password' => 'secret-pass-1'];

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
        Http::assertNothingSent();

        $this->post(route('login.store'), $credentials + ['cf-turnstile-response' => 'bad'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), $credentials + ['cf-turnstile-response' => 'good'])->assertRedirect();
        $this->assertAuthenticated();
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'good');
    }

    public function test_with_keys_registration_needs_the_check_too(): void
    {
        $this->keys();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->post(route('register.store'), ['name' => 'Some School', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('name');
    }
}
