<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_box_accepts_text_so_a_username_can_be_typed(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Email or username')->assertDontSee('type="email"', false);
    }

    public function test_a_user_signs_in_with_the_username_in_any_letter_case_or_with_the_email(): void
    {
        $user = User::factory()->create(['username' => 'rm.sorong', 'email' => 'rm@example.test', 'password' => 'secret-pass-1', 'status' => 'active', 'role' => 'master_user', 'school_id' => null, 'organization_id' => null]);

        foreach (['rm.sorong', 'RM.Sorong', 'rm@example.test'] as $login) {
            $response = $this->post(route('login.store'), ['email' => $login, 'password' => 'secret-pass-1']);
            $this->assertSame(302, $response->status(), $login.' => '.$response->status());
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }

        $this->post(route('login.store'), ['email' => 'rm.sorong', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
