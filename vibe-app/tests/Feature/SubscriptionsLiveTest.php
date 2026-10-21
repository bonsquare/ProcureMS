<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PaymentProof;
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

    public function test_a_new_proof_reaches_the_open_page_and_leaves_it_when_decided(): void
    {
        $subscription = $this->subscribe('EEE', 'trial');
        $user = User::factory()->create(['organization_id' => $subscription->organization_id, 'school_id' => $subscription->school_id, 'role' => 'school_admin']);
        $hash = $this->actingAs($this->master())->get(route('subscriptions'))->assertSee('No proof of payment is waiting')->viewData('liveHash');

        $proof = PaymentProof::withoutGlobalScopes()->create(['organization_id' => $subscription->organization_id, 'school_id' => $subscription->school_id, 'user_id' => $user->id, 'reference' => 'REF-LIVE', 'paid_at' => now()->toDateString(), 'list_price' => 1000, 'discount_amount' => 0, 'amount_to_pay' => 1000, 'drive_file_id' => 'f1', 'drive_link' => 'https://drive.google.com/file/d/f1/view', 'file_name' => 'proof.png', 'mime' => 'image/png', 'size' => 10, 'sha256' => str_repeat('a', 64)]);

        $changed = $this->getJson(route('subscriptions.live', ['h' => $hash]))->assertOk();
        $this->assertStringContainsString('REF-LIVE', $changed->json('html'));
        $this->assertStringContainsString('https://drive.google.com/file/d/f1/view', $changed->json('html'));
        $this->assertStringContainsString('data-sub-open="approve-proof"', $changed->json('html'));

        $proof->update(['status' => 'approved']);
        $after = $this->getJson(route('subscriptions.live', ['h' => $changed->json('hash')]))->assertOk();
        $this->assertStringNotContainsString('REF-LIVE', $after->json('html'));
    }

    public function test_only_who_can_open_subscriptions_gets_the_live_data(): void
    {
        $subscription = $this->subscribe('DDD');
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $subscription->organization_id, 'school_id' => $subscription->school_id]);

        $this->actingAs($user)->getJson(route('subscriptions.live'))->assertForbidden();
    }
}
