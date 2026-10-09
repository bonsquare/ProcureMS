<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateMasterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_master_user_with_a_strong_generated_password(): void
    {
        $this->artisan('master:create', ['email' => 'master@example.com', '--name' => 'System Master'])
            ->expectsOutputToContain('master@example.com')
            ->assertSuccessful();

        $user = User::where('email', 'master@example.com')->firstOrFail();
        $this->assertSame('master_user', $user->role);
        $this->assertSame('System Master', $user->name);
        $this->assertNull($user->school_id);
        $this->assertNull($user->organization_id);
        $this->assertSame('active', $user->status ?: 'active');
        $this->assertNotEmpty($user->username);
        $this->assertFalse(Hash::check('password', $user->password), 'never the default password');
    }

    public function test_the_generated_password_is_printed_once_and_works(): void
    {
        $shown = null;
        $this->artisan('master:create', ['email' => 'once@example.com'])
            ->expectsOutputToContain('Password:')
            ->assertSuccessful();

        // A password given with --password is used as is.
        $this->artisan('master:create', ['email' => 'given@example.com', '--password' => 'a-Long-Passphrase-2026'])->assertSuccessful();
        $this->assertTrue(Hash::check('a-Long-Passphrase-2026', User::where('email', 'given@example.com')->value('password')));
        $this->post(route('login.store'), ['email' => 'given@example.com', 'password' => 'a-Long-Passphrase-2026'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_it_refuses_a_short_password_and_an_existing_email(): void
    {
        $this->artisan('master:create', ['email' => 'short@example.com', '--password' => 'abc123'])->assertFailed();
        $this->assertNull(User::where('email', 'short@example.com')->first());

        $this->artisan('master:create', ['email' => 'dup@example.com'])->assertSuccessful();
        $this->artisan('master:create', ['email' => 'dup@example.com'])->assertFailed();
        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
    }
}
