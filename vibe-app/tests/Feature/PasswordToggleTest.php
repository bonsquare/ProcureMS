<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_with_a_password_field_loads_the_show_password_script(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-password-toggle-script', false);
        $this->get(route('register'))->assertOk()->assertSee('data-password-toggle-script', false);

        $master = User::factory()->create(['role' => 'master_user']);
        $this->actingAs($master)->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('data-password-toggle-script', false)->assertSee('type="password"', false);
    }

    public function test_the_script_is_loaded_once_per_page(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-password-toggle-script'));
    }

    public function test_the_login_page_never_shows_demo_credentials_outside_the_local_environment(): void
    {
        $this->assertFalse(app()->environment('local'));

        $this->get(route('login'))->assertOk()->assertDontSee('Demo Master Admin')->assertDontSee('admin@procurems.test');
    }
}
