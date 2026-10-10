<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyConsentTest extends TestCase
{
    use RefreshDatabase;

    private function school(array $extra = []): array
    {
        return [
            'name' => 'Privacy Test School', 'system_user_given_name' => 'Pia', 'system_user_surname' => 'Santos', 'system_user_username' => 'pia.santos',
            'system_user_position' => 'Principal', 'system_user_email' => 'pia@example.test', 'system_user_phone' => '09170000009',
            'system_user_password' => 'secret-pass-1', 'system_user_password_confirmation' => 'secret-pass-1', 'system_user_confirmed' => '1',
            ...$extra,
        ];
    }

    public function test_the_form_shows_the_privacy_policy_and_the_confirmation_dialog_wiring(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('Privacy Policy')->assertSee('name="privacy_accepted"', false)
            ->assertSee('identity-confirm', false)->assertSee('confirm-submit', false);
    }

    public function test_a_new_school_registration_needs_the_privacy_policy_accepted(): void
    {
        $this->post(route('register.store'), $this->school())->assertSessionHasErrors('privacy_accepted');
        $this->assertSame(0, User::where('email', 'pia@example.test')->count());
    }

    public function test_a_takeover_registration_needs_the_privacy_policy_accepted(): void
    {
        $this->post(route('register.store'), $this->school(['registration_type' => 'takeover']))->assertSessionHasErrors('privacy_accepted');
        $this->assertSame(0, User::where('email', 'pia@example.test')->count());
    }

    public function test_acceptance_is_saved_with_its_date_time_and_policy_version(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(9, 30));
        $this->post(route('register.store'), $this->school(['privacy_accepted' => '1']))->assertRedirect(route('login'));

        $user = User::withoutGlobalScopes()->where('email', 'pia@example.test')->firstOrFail();
        $this->assertSame('2026-10-11 09:30:00', $user->privacy_accepted_at->format('Y-m-d H:i:s'));
        $this->assertSame(User::PRIVACY_POLICY_VERSION, $user->privacy_policy_version);
        $this->assertNotNull(AuditLog::where('action', 'submitted_school_pre_registration')->first()->metadata['privacy_accepted_at'] ?? null);
    }

    public function test_a_takeover_acceptance_is_saved_too(): void
    {
        $this->post(route('register.store'), $this->school(['registration_type' => 'takeover', 'privacy_accepted' => '1']))->assertRedirect(route('login'));

        $user = User::withoutGlobalScopes()->where('email', 'pia@example.test')->firstOrFail();
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertSame(User::PRIVACY_POLICY_VERSION, $user->privacy_policy_version);
    }
}
