<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionIncomeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'In', 'slug' => 'in', 'status' => 'active']);
        $this->school = School::create(['organization_id' => $this->organization->id, 'code' => 'IN1', 'name' => 'Income School', 'status' => 'active']);
    }

    private function pay(float $amount, string $paidAt): SubscriptionPayment
    {
        return SubscriptionPayment::withoutGlobalScopes()->create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'amount' => $amount, 'list_price' => 1000, 'discount_amount' => 1000 - $amount, 'paid_at' => $paidAt, 'period_start' => $paidAt, 'period_end' => date('Y-m-d', strtotime($paidAt.' +1 year'))]);
    }

    public function test_gross_is_the_sum_of_amounts_really_paid(): void
    {
        $this->pay(1000, '2026-03-01');
        $this->pay(600, '2026-04-01');

        $this->assertSame(1600.0, app(SubscriptionIncomeService::class)->summary(null)['gross']);
    }

    public function test_the_division_is_30_20_50_and_adds_up_to_the_gross(): void
    {
        $this->pay(1000, '2026-03-01');
        $this->pay(333.33, '2026-04-01');
        $summary = app(SubscriptionIncomeService::class)->summary(null);

        $this->assertSame(1333.33, $summary['gross']);
        $this->assertSame(400.0, $summary['system_subscription']);
        $this->assertSame(266.67, $summary['operating']);
        $this->assertSame(666.66, $summary['net']);
        $this->assertSame($summary['gross'], round($summary['system_subscription'] + $summary['operating'] + $summary['net'], 2));
    }

    public function test_the_division_of_nothing_is_nothing(): void
    {
        $summary = app(SubscriptionIncomeService::class)->summary(null);

        $this->assertSame([0.0, 0.0, 0.0, 0.0], [$summary['gross'], $summary['system_subscription'], $summary['operating'], $summary['net']]);
    }

    public function test_a_year_filters_by_the_payment_date(): void
    {
        $this->pay(1000, '2025-12-31');
        $this->pay(700, '2026-01-01');
        $this->pay(500, '2026-12-31');

        $service = app(SubscriptionIncomeService::class);
        $this->assertSame(1200.0, $service->summary(2026)['gross']);
        $this->assertSame(1000.0, $service->summary(2025)['gross']);
        $this->assertSame(2200.0, $service->summary(null)['gross']);
        $this->assertSame([2026, 2025], $service->years());
    }

    public function test_users_registered_and_activation_requests_counts(): void
    {
        User::factory()->count(2)->create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $waiting = Organization::create(['name' => 'Wt', 'slug' => 'wt', 'status' => 'inactive']);
        School::create(['organization_id' => $waiting->id, 'code' => 'WT1', 'name' => 'Waiting School', 'status' => 'inactive']);
        School::create(['organization_id' => $waiting->id, 'code' => 'WT2', 'name' => 'Waiting Two', 'status' => 'inactive']);

        $summary = app(SubscriptionIncomeService::class)->summary(null);

        $this->assertSame(2, $summary['users'], 'the master accounts are not school users');
        $this->assertSame(2, $summary['activation_requests']);
    }

    public function test_only_the_master_and_a_sub_master_with_access_see_the_section(): void
    {
        $this->pay(1000, now()->toDateString());
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master)->get(route('home'))->assertOk()->assertSee('data-subscription-income', false)->assertSee('1,000.00')->assertSee('300.00')->assertSee('200.00')->assertSee('500.00')->assertSee('System Subscription')->assertSee('Allowance, Maintenance and Operating Expense')->assertSee('Net Income');

        auth()->logout();
        $with = User::factory()->create(['role' => 'sub_master', 'organization_id' => null, 'school_id' => null, 'access' => ['subscriptions']]);
        $this->actingAs($with)->get(route('home'))->assertOk()->assertSee('data-subscription-income', false);

        auth()->logout();
        $without = User::factory()->create(['role' => 'sub_master', 'organization_id' => null, 'school_id' => null, 'access' => ['schools']]);
        $this->actingAs($without)->get(route('home'))->assertOk()->assertDontSee('data-subscription-income', false);

        auth()->logout();
        $school = User::factory()->create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        $this->actingAs($school)->get(route('home'))->assertOk()->assertDontSee('data-subscription-income', false);
    }

    public function test_the_year_choice_changes_the_figures(): void
    {
        $this->pay(1000, '2025-06-01');
        $this->pay(600, '2026-06-01');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('home', ['income_year' => 2025]))->assertSee('data-income-gross="1000.00"', false);
        $this->get(route('home', ['income_year' => 2026]))->assertSee('data-income-gross="600.00"', false);
        $this->get(route('home'))->assertSee('data-income-gross="1600.00"', false);
    }
}
