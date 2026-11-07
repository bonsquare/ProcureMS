<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ActionPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_sets_and_resets_the_pin_with_the_login_password(): void
    {
        $user = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null, 'pin' => null]);

        $this->actingAs($user)->post(route('account.pin'), ['pin' => '654321', 'pin_confirmation' => '654321', 'password' => 'wrong'])->assertSessionHasErrors('password', errorBag: 'pin');
        $this->assertNull($user->fresh()->pin);

        $this->post(route('account.pin'), ['pin' => '65432', 'pin_confirmation' => '65432', 'password' => 'password'])->assertSessionHasErrors('pin', errorBag: 'pin');
        $this->post(route('account.pin'), ['pin' => '654321', 'pin_confirmation' => '111111', 'password' => 'password'])->assertSessionHasErrors('pin', errorBag: 'pin');

        $this->post(route('account.pin'), ['pin' => '654321', 'pin_confirmation' => '654321', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('654321', $user->fresh()->pin));

        // Forgot it: the same form resets it.
        $this->postJson(route('account.pin'), ['pin' => '222333', 'pin_confirmation' => '222333', 'password' => 'password'])->assertOk();
        $this->assertTrue(Hash::check('222333', $user->fresh()->pin));
    }

    public function test_the_pin_is_never_stored_as_text(): void
    {
        $user = User::factory()->create();
        $this->assertNotSame('123456', $user->getRawOriginal('pin'));
        $this->assertArrayNotHasKey('pin', $user->toArray());
    }

    public function test_an_edit_is_confirmed_with_the_pin_and_not_the_password(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->post(route('promo-codes.store'), ['reason' => 'x', 'pin' => 'password'])->assertSessionHasErrors('pin');
        $this->post(route('promo-codes.store'), ['reason' => 'x'])->assertSessionHasErrors('pin');
    }

    public function test_a_user_without_a_pin_is_told_to_set_one_and_the_guard_offers_it(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null, 'pin' => null]);

        $this->actingAs($master)->post(route('promo-codes.store'), ['reason' => 'x', 'pin' => '123456'])->assertSessionHasErrors(['pin' => 'You have no PIN yet. Set your PIN first.']);
        $this->get(route('subscriptions'))->assertOk()->assertSee('action-guard-setpin', false)->assertSee('Forgot your PIN?');
    }

    public function test_five_wrong_pins_lock_the_check_for_a_while(): void
    {
        RateLimiter::clear('pin-check:1');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        RateLimiter::clear('pin-check:'.$master->id);

        $this->actingAs($master);
        foreach (range(1, 5) as $ignored) {
            $this->post(route('promo-codes.store'), ['reason' => 'x', 'pin' => '000000'])->assertSessionHasErrors(['pin' => 'The PIN is wrong.']);
        }
        $this->post(route('promo-codes.store'), ['reason' => 'x', 'pin' => '123456'])->assertSessionHasErrors('pin');
        $this->assertStringContainsString('Too many wrong PINs', session('errors')->first('pin'));
    }

    public function test_the_system_users_page_has_the_pin_button_for_the_own_account(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'S1', 'name' => 'School One', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id, 'status' => 'active', 'pin' => null]);
        $this->actingAs($admin)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'users']))->assertOk()->assertSee('PIN not set')->assertSee('Set your PIN');
    }
}
