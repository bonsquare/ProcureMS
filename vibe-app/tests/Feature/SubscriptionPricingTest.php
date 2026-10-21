<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\School;
use App\Models\Subscription;
use App\Services\SubscriptionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionPricingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $organization = Organization::create(['name' => 'Pr', 'slug' => 'pr', 'status' => 'active']);
        $this->school = School::create(['organization_id' => $organization->id, 'code' => 'PR1', 'name' => 'Price School', 'status' => 'active']);
    }

    private function subscription(array $overrides = []): Subscription
    {
        return Subscription::create($overrides + ['organization_id' => $this->school->organization_id, 'school_id' => $this->school->id, 'plan' => 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'pending', 'status' => 'trial', 'starts_at' => now(), 'subscription_end' => now()->addDays(3)]);
    }

    private function promo(array $overrides = []): PromoCode
    {
        return PromoCode::create($overrides + ['code' => 'SAVE', 'type' => 'percent', 'value' => 20, 'active' => true]);
    }

    private function quote(?string $code = null): array
    {
        return app(SubscriptionPricing::class)->quote($this->school, $code);
    }

    public function test_list_price_is_1000(): void
    {
        $this->assertSame(1000.00, SubscriptionPricing::LIST_PRICE);
        $quote = $this->quote();

        $this->assertSame([1000.0, 0.0, 1000.0, 'list'], [$quote['list_price'], $quote['discount'], $quote['amount'], $quote['source']]);
        $this->assertNull($quote['promo']);
    }

    public function test_the_price_set_for_a_school_wins_over_a_promo(): void
    {
        $this->subscription(['charge_amount' => 600]);
        $this->promo(['value' => 50]);

        $quote = $this->quote('save');

        $this->assertSame([1000.0, 400.0, 600.0, 'school'], [$quote['list_price'], $quote['discount'], $quote['amount'], $quote['source']]);
        $this->assertNull($quote['promo'], 'the master price is used, the promo is not');
    }

    public function test_a_promo_applies_only_when_the_school_has_no_price(): void
    {
        $this->subscription();
        $promo = $this->promo();

        $quote = $this->quote('SAVE');

        $this->assertSame([800.0, 200.0, 'promo'], [$quote['amount'], $quote['discount'], $quote['source']]);
        $this->assertTrue($quote['promo']->is($promo));
    }

    public function test_percent_and_fixed_promos(): void
    {
        $this->promo(['code' => 'PCT', 'type' => 'percent', 'value' => 25]);
        $this->promo(['code' => 'FIX', 'type' => 'fixed', 'value' => 150]);

        $this->assertSame(750.0, $this->quote('pct')['amount']);
        $this->assertSame(850.0, $this->quote('fix')['amount']);
    }

    public function test_an_ended_unused_or_inactive_promo_is_refused(): void
    {
        $this->promo(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        $this->promo(['code' => 'LATER', 'starts_at' => now()->addDay()]);
        $this->promo(['code' => 'OFF', 'active' => false]);
        $this->promo(['code' => 'FULL', 'max_uses' => 2, 'used_count' => 2]);
        $this->promo(['code' => 'TODAY', 'ends_at' => now()]);

        foreach (['OLD', 'LATER', 'OFF', 'FULL', 'NOPE'] as $code) {
            try {
                $this->quote($code);
                $this->fail("{$code} should be refused");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('promo_code', $exception->errors());
            }
        }
        $this->assertSame(800.0, $this->quote('TODAY')['amount'], 'the end date itself is still valid');
    }

    public function test_the_amount_is_never_below_one_peso(): void
    {
        $this->promo(['code' => 'ALL', 'type' => 'fixed', 'value' => 5000]);

        $this->assertSame(1.0, $this->quote('ALL')['amount']);
    }

    public function test_a_school_price_after_its_end_date_is_ignored(): void
    {
        $this->subscription(['charge_amount' => 600, 'charge_ends_at' => now()->subDay()]);
        $this->assertSame(1000.0, $this->quote()['amount']);

        Subscription::query()->update(['charge_ends_at' => now()]);
        $this->assertSame(600.0, $this->quote()['amount'], 'the end date itself still counts');
    }
}
