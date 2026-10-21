<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionsLiveTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function subscribe(string $code, string $status = 'active'): Subscription
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);

        return Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'paid', 'status' => $status, 'starts_at' => now(), 'subscription_end' => now()->addYear()]);
    }

    public function test_the_page_follows_its_data_and_answers_nothing_when_nothing_changed(): void
    {
        $this->subscribe('AAA');
        $page = $this->actingAs($this->master())->get(route('subscriptions'))->assertOk()->assertSee('data-live-body', false)->assertSee(route('subscriptions.live'));
        $hash = $page->viewData('liveHash');

        $this->getJson(route('subscriptions.live', ['h' => $hash]))->assertNoContent();

        $this->subscribe('BBB');
        $changed = $this->getJson(route('subscriptions.live', ['h' => $hash]))->assertOk()->assertJsonStructure(['hash', 'html']);
        $this->assertNotSame($hash, $changed->json('hash'));
        $this->assertStringContainsString('BBB School', $changed->json('html'));
        $this->getJson(route('subscriptions.live', ['h' => $changed->json('hash')]))->assertNoContent();
    }

    public function test_a_status_change_reaches_the_open_page(): void
    {
        $subscription = $this->subscribe('CCC');
        $hash = $this->actingAs($this->master())->get(route('subscriptions'))->viewData('liveHash');

        $subscription->update(['status' => 'canceled']);

        $this->getJson(route('subscriptions.live', ['h' => $hash]))->assertOk()->assertJsonPath('hash', fn ($new) => $new !== $hash);
    }

    public function test_only_who_can_open_subscriptions_gets_the_live_data(): void
    {
        $subscription = $this->subscribe('DDD');
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $subscription->organization_id, 'school_id' => $subscription->school_id]);

        $this->actingAs($user)->getJson(route('subscriptions.live'))->assertForbidden();
    }
}
