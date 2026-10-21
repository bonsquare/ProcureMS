<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\PaymentProof;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function schoolUser(string $status = 'active', ?int $daysLeft = 200, string $role = 'school_admin', string $code = 'N1'): User
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => $status === 'trial' ? 'trial' : 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'pending', 'status' => $status, 'starts_at' => now()->subDays(5), 'subscription_end' => now()->addDays($daysLeft), 'grace_period_end' => $status === 'grace_period' ? now()->addDays(3) : null]);

        return $user;
    }

    private function titles(User $user): array
    {
        return collect($this->actingAs($user)->getJson(route('notifications'))->assertOk()->json('items'))->pluck('title')->all();
    }

    private function report(User $user, array $overrides): LiquidationReport
    {
        return LiquidationReport::create($overrides + ['organization_id' => $user->organization_id, 'school_id' => $user->school_id, 'report_number' => 'LR-'.uniqid(), 'ors_number' => 'ORS-'.uniqid(), 'amount' => 100, 'status' => 'approved', 'payee' => 'Payee', 'purpose' => 'Purpose']);
    }

    public function test_every_user_gets_the_subscription_notice(): void
    {
        $this->assertContains('Trial: 2 days left', $this->titles($this->schoolUser('trial', 2, 'school_admin', 'T1')));
        auth()->logout();
        $this->assertContains('Trial: ends today', $this->titles($this->schoolUser('trial', 0, 'school_admin', 'T2')));
        auth()->logout();
        $this->assertContains('Your Annual plan ends in 12 days', $this->titles($this->schoolUser('active', 12, 'school_admin', 'T3')));
        auth()->logout();
        $this->assertSame([], array_filter($this->titles($this->schoolUser('active', 200, 'school_admin', 'T4')), fn ($title) => str_contains($title, 'plan')));
        auth()->logout();
        $this->assertContains('Your Free plan ends in 5 days', $this->titles($this->schoolUser('free', 5, 'school_admin', 'T5')));
        auth()->logout();
        $this->assertSame([], array_filter($this->titles($this->schoolUser('free', 30, 'school_admin', 'T6')), fn ($title) => str_contains($title, 'Free')));
        auth()->logout();
        $this->assertContains('Your subscription has expired: the school is read-only', $this->titles($this->schoolUser('expired', -3, 'school_admin', 'T7')));
        auth()->logout();
        $this->assertContains('Your subscription is suspended: the school is read-only', $this->titles($this->schoolUser('suspended', 100, 'school_admin', 'T8')));
        auth()->logout();
        $this->assertContains('Grace period: pay to keep the school writable', $this->titles($this->schoolUser('grace_period', -1, 'school_admin', 'T9')));
    }

    public function test_the_master_gets_activation_requests_and_proofs_to_review(): void
    {
        $organization = Organization::create(['name' => 'W', 'slug' => 'w', 'status' => 'inactive']);
        $waiting = School::create(['organization_id' => $organization->id, 'code' => 'WAIT', 'name' => 'Waiting School', 'status' => 'inactive']);
        $user = $this->schoolUser('active', 200, 'school_admin', 'M1');
        PaymentProof::withoutGlobalScopes()->create(['organization_id' => $user->organization_id, 'school_id' => $user->school_id, 'user_id' => $user->id, 'reference' => 'R', 'paid_at' => now()->toDateString(), 'list_price' => 1000, 'discount_amount' => 0, 'amount_to_pay' => 1000, 'drive_file_id' => 'f', 'drive_link' => 'l', 'file_name' => 'p.png', 'mime' => 'image/png', 'size' => 1, 'sha256' => str_repeat('b', 64)]);
        auth()->logout();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $response = $this->actingAs($master)->getJson(route('notifications'))->assertOk();

        $items = collect($response->json('items'));
        $this->assertContains('1 school waiting for activation', $items->pluck('title')->all());
        $this->assertContains('1 proof of payment to review', $items->pluck('title')->all());
        $this->assertSame(route('subscriptions'), $items->firstWhere('title', '1 proof of payment to review')['url']);
        $this->assertSame($items->count(), $response->json('count'));
        $this->assertNotNull($waiting);
    }

    public function test_school_users_get_returned_ors_dvs_waiting_and_missing_papers_by_permission(): void
    {
        $user = $this->schoolUser('active', 200, 'school_admin', 'S1');
        $this->report($user, ['status' => 'returned']);
        $this->report($user, ['status' => 'approved', 'dv_number' => 'DV-1']);
        $this->report($user, ['status' => 'approved', 'dv_number' => 'DV-2', 'paid_at' => now()]);
        $this->report($user, ['status' => 'pending_documents']);

        $titles = $this->titles($user);

        $this->assertContains('1 ORS returned to you', $titles);
        $this->assertContains('1 DV waiting for payment', $titles);
        $this->assertContains('1 ORS with missing papers', $titles);

        auth()->logout();
        $inspector = $this->schoolUser('active', 200, 'inspector', 'S2');
        $this->report($inspector, ['status' => 'returned']);
        $this->report($inspector, ['status' => 'approved', 'dv_number' => 'DV-3']);
        $this->assertSame([], array_filter($this->titles($inspector), fn ($title) => str_contains($title, 'ORS') || str_contains($title, 'DV')));
    }

    public function test_notifications_stay_in_the_users_school_scope(): void
    {
        $mine = $this->schoolUser('active', 200, 'school_admin', 'SC1');
        auth()->logout();
        $other = $this->schoolUser('active', 200, 'school_admin', 'SC2');
        $this->report($other, ['status' => 'returned']);

        $this->assertSame([], array_filter($this->titles($mine), fn ($title) => str_contains($title, 'returned')));
    }

    public function test_server_time_is_philippine_time(): void
    {
        $this->actingAs($this->schoolUser())->getJson(route('server-time'))->assertOk()->assertJsonPath('timezone', 'Asia/Manila')->assertJson(fn ($json) => $json->where('now', fn ($value) => str_ends_with($value, '+08:00'))->etc());
    }

    public function test_the_pages_include_the_clock_and_one_bell(): void
    {
        $html = $this->actingAs($this->schoolUser())->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-topbar-tools-script'));
        $this->assertStringContainsString(trim(json_encode(route('notifications')), '"'), $html);
        $this->assertStringContainsString(trim(json_encode(route('server-time')), '"'), $html);
        auth()->logout();
        $this->actingAs($this->schoolUser('active', 200, 'school_admin', 'B2'))->get(route('subscription'))->assertSee('data-topbar-tools-script', false);
    }

    public function test_guests_do_not_get_the_widget_or_the_feed(): void
    {
        auth()->logout();
        $this->getJson(route('notifications'))->assertUnauthorized();
        $this->get(route('login'))->assertOk()->assertDontSee('data-topbar-tools-script', false);
    }
}
