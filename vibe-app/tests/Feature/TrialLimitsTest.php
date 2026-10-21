<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function schoolUser(string $status, string $code, int $daysLeft = 2): User
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => $status === 'trial' ? 'trial' : 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'pending', 'status' => $status, 'starts_at' => now(), 'subscription_end' => now()->addDays($daysLeft)]);

        return $user;
    }

    public function test_a_trial_cannot_record_a_payment_or_cancel_a_check(): void
    {
        $this->actingAs($this->schoolUser('trial', 'TL1'));

        $this->post(route('cash.pay', ['liquidationReport' => 1]))->assertStatus(402)->assertSee('Recording a payment is available on a paid subscription');
        $this->post(route('cash.cancel', ['liquidationReport' => 1]))->assertStatus(402);
        $this->patch(route('cash.payment.update', ['liquidationReport' => 1]))->assertStatus(402);
    }

    public function test_a_trial_cannot_upload_a_general_file_or_download_a_backup(): void
    {
        $this->actingAs($this->schoolUser('trial', 'TL2'));

        $this->post(route('drive-files.store'))->assertStatus(402)->assertSee('Uploading files to Google Drive is available on a paid subscription');
        $this->get(route('backup.download', ['backupRun' => 1]))->assertStatus(402);
        $this->post(route('backup.run'))->assertStatus(402);
    }

    public function test_the_trial_banner_shows_the_days_left(): void
    {
        $this->actingAs($this->schoolUser('trial', 'TL3', 2))->get(route('home'))->assertOk()->assertSee('data-trial-banner', false)->assertSee('2 days left');
        auth()->logout();
        $this->actingAs($this->schoolUser('trial', 'TL4', 0))->get(route('home'))->assertSee('ends today');
        auth()->logout();
        $this->actingAs($this->schoolUser('trial', 'TL5', 1))->get(route('home'))->assertSee('1 day left');
    }

    public function test_free_active_and_master_are_not_limited_and_see_no_banner(): void
    {
        foreach (['active', 'free'] as $status) {
            auth()->logout();
            $this->actingAs($this->schoolUser($status, 'TM'.strtoupper(substr($status, 0, 1))));
            $this->get(route('home'))->assertOk()->assertDontSee('data-trial-banner', false);
            $this->assertNotSame(402, $this->post(route('drive-files.store'))->getStatusCode());
            $this->assertNotSame(402, $this->post(route('cash.pay', ['liquidationReport' => 1]))->getStatusCode());
        }
        auth()->logout();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master)->get(route('home'))->assertOk()->assertDontSee('data-trial-banner', false);
    }
}
