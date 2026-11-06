<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionNextBillingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $end, ?string $grace = null, string $code = 'NB'): User
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'status' => 'active', 'starts_at' => '2025-10-24', 'subscription_end' => $end, 'grace_period_end' => $grace]);

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_page_shows_the_next_billing_period_with_its_amount_billing_date_and_due_date(): void
    {
        Carbon::setTestNow('2026-11-06');

        $this->actingAs($this->admin('2028-10-23'))->get(route('subscription'))->assertOk()
            ->assertSeeInOrder(['Next billing period', 'Not open yet', '1,000.00', 'Oct 24, 2028 to Oct 23, 2029', 'Billing date', 'Sep 23, 2028', 'Due date', 'Oct 23, 2028']);
    }

    public function test_the_bill_is_open_in_the_last_thirty_days_and_overdue_after_the_due_date(): void
    {
        Carbon::setTestNow('2026-11-06');
        $this->actingAs($this->admin('2026-11-20', '2026-12-05'))->get(route('subscription'))->assertOk()
            ->assertSee('Open: pay before the due date')->assertSee('Grace period ends')->assertSee('Dec 5, 2026')->assertSee('14 days to the due date');

        auth()->logout();
        Carbon::setTestNow('2026-12-01');
        $this->actingAs($this->admin('2026-11-20', null, 'NB2'))->get(route('subscription'))->assertOk()->assertSee('Overdue')->assertSee('11 days past the due date');
    }
}
