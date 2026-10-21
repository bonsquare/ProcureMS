<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $organization = Organization::create(['name' => 'Ad', 'slug' => 'ad', 'status' => 'active']);
        $this->school = School::create(['organization_id' => $organization->id, 'code' => 'AD1', 'name' => 'Admin School', 'status' => 'active']);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        $this->master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        app(SubscriptionService::class)->startTrial($this->school, $owner);
        $this->actingAs($this->master);
    }

    private function sub(): Subscription
    {
        return Subscription::withoutGlobalScopes()->where('school_id', $this->school->id)->latest('id')->firstOrFail();
    }

    private function guard(array $data = [], string $reason = 'Because'): array
    {
        return $data + ['reason' => $reason, 'password' => 'password'];
    }

    public function test_activate_records_the_payment_the_school_owes_and_makes_it_annual(): void
    {
        $this->post(route('subscriptions.price', $this->school), $this->guard(['amount' => '600']))->assertSessionHasNoErrors();

        $this->post(route('subscriptions.activate', $this->school), $this->guard(['reference' => 'GCASH-77', 'paid_at' => now()->toDateString()]))->assertSessionHasNoErrors()->assertRedirect();

        $payment = SubscriptionPayment::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['600.00', '400.00', 'GCASH-77'], [$payment->amount, $payment->discount_amount, $payment->reference]);
        $this->assertSame(['annual', 'active'], [$this->sub()->plan, $this->sub()->status]);
    }

    public function test_free_extend_suspend_reactivate_and_price_work(): void
    {
        $this->post(route('subscriptions.free.grant', $this->school), $this->guard(['ends_at' => now()->addMonth()->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('free', $this->sub()->status);

        $this->post(route('subscriptions.extend', $this->school), $this->guard(['days' => 5]))->assertSessionHasNoErrors();
        $this->assertSame(now()->addMonth()->addDays(5)->toDateString(), $this->sub()->subscription_end->toDateString());

        $this->post(route('subscriptions.suspend', $this->school), $this->guard())->assertSessionHasNoErrors();
        $this->assertSame('suspended', $this->sub()->status);
        $this->post(route('subscriptions.reactivate', $this->school), $this->guard())->assertSessionHasNoErrors();
        $this->assertSame('free', $this->sub()->status);

        $this->post(route('subscriptions.free.remove', $this->school), $this->guard())->assertSessionHasNoErrors();
        $this->assertSame('trial', $this->sub()->status, 'back to what it was before Free');

        $this->post(route('subscriptions.price', $this->school), $this->guard(['amount' => '750', 'ends_at' => now()->addMonths(2)->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('750.00', $this->sub()->charge_amount);
        $this->post(route('subscriptions.price', $this->school), $this->guard(['amount' => '']))->assertSessionHasNoErrors();
        $this->assertNull($this->sub()->charge_amount);
    }

    public function test_create_subscription_starts_a_trial_or_records_an_annual_payment_for_a_school(): void
    {
        $other = School::create(['organization_id' => $this->school->organization_id, 'code' => 'AD2', 'name' => 'Second School', 'status' => 'active']);
        $third = School::create(['organization_id' => $this->school->organization_id, 'code' => 'AD3', 'name' => 'Third School', 'status' => 'active']);

        $this->post(route('subscriptions.store'), $this->guard(['school_id' => $other->id, 'mode' => 'trial']))->assertSessionHasNoErrors();
        $this->assertSame('trial', Subscription::withoutGlobalScopes()->where('school_id', $other->id)->firstOrFail()->status);

        $this->post(route('subscriptions.store'), $this->guard(['school_id' => $third->id, 'mode' => 'annual', 'reference' => 'CASH-1', 'paid_at' => now()->toDateString()]))->assertSessionHasNoErrors();
        $this->assertSame('active', Subscription::withoutGlobalScopes()->where('school_id', $third->id)->firstOrFail()->status);
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->where('school_id', $third->id)->count());
    }

    public function test_every_action_needs_a_reason_and_the_users_own_password(): void
    {
        foreach (['subscriptions.suspend', 'subscriptions.reactivate', 'subscriptions.extend', 'subscriptions.activate', 'subscriptions.free.grant', 'subscriptions.free.remove', 'subscriptions.price'] as $route) {
            $payload = ['days' => 3, 'amount' => '500', 'ends_at' => now()->addMonth()->toDateString(), 'reference' => 'R', 'paid_at' => now()->toDateString()];
            $this->post(route($route, $this->school), $payload + ['reason' => '', 'password' => 'password'])->assertSessionHasErrors('reason');
            $this->post(route($route, $this->school), $payload + ['reason' => 'Why', 'password' => 'not-it'])->assertSessionHasErrors('password');
        }
        $this->assertSame('trial', $this->sub()->status, 'nothing was changed');
        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('action', 'like', 'subscription_%')->count());
    }

    public function test_a_sub_master_cannot_grant_free_or_set_a_price_but_can_suspend(): void
    {
        auth()->logout();
        $sub = User::factory()->create(['role' => 'sub_master', 'organization_id' => null, 'school_id' => null, 'access' => ['subscriptions']]);
        $this->actingAs($sub);

        $this->post(route('subscriptions.free.grant', $this->school), $this->guard(['ends_at' => now()->addMonth()->toDateString()]))->assertForbidden();
        $this->post(route('subscriptions.price', $this->school), $this->guard(['amount' => '500']))->assertForbidden();
        $this->post(route('subscriptions.suspend', $this->school), $this->guard())->assertSessionHasNoErrors();
        $this->assertSame('suspended', $this->sub()->status);
    }

    public function test_a_school_user_cannot_use_any_action(): void
    {
        auth()->logout();
        $user = User::factory()->create(['organization_id' => $this->school->organization_id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        $this->actingAs($user);

        $this->post(route('subscriptions.suspend', $this->school), $this->guard())->assertForbidden();
        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_the_view_window_and_the_history_list_what_was_done(): void
    {
        $this->post(route('subscriptions.suspend', $this->school), $this->guard([], 'Not paid'))->assertSessionHasNoErrors();

        $this->getJson(route('subscriptions.history', $this->school))->assertOk()->assertJsonPath('entries.0.action', 'Subscription Suspended')->assertJsonPath('entries.0.reason', 'Not paid');
        $this->getJson(route('subscriptions.show', $this->school))->assertOk()->assertJsonPath('school', 'Admin School')->assertJsonPath('status', 'suspended')->assertJsonStructure(['plan', 'amount_to_pay', 'list_price', 'dates', 'payments']);
    }

    public function test_the_page_has_a_working_button_for_each_action_and_follows_the_change(): void
    {
        $hash = $this->get(route('subscriptions'))->assertOk()->assertSee('data-sub-open="activate"', false)->assertSee('data-sub-open="extend"', false)->assertSee('data-sub-open="suspend"', false)->assertSee('data-sub-open="free"', false)->assertSee('data-sub-open="price"', false)->assertSee('data-sub-open="create"', false)->assertSee('data-history', false)->viewData('liveHash');

        $this->post(route('subscriptions.suspend', $this->school), $this->guard())->assertSessionHasNoErrors();

        $this->getJson(route('subscriptions.live', ['h' => $hash]))->assertOk();
    }
}
