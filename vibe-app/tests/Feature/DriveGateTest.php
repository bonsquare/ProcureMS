<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\GoogleDriveConnection;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DriveGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', 'auth', 'drive.connected'])->post('/__drive-guarded', fn () => response('uploaded'))->name('test.drive-guarded');
    }

    private function user(): User
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function connect(User $user, string $status): void
    {
        GoogleDriveConnection::create([
            'user_id' => $user->id, 'access_token' => 'token', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(),
            'root_folder_id' => 'root', 'folder_ids' => ['Backup' => 'b', 'Logo' => 'l', 'Files' => 'f'], 'status' => $status, 'connected_at' => now(),
        ]);
    }

    public function test_a_user_without_drive_is_redirected_from_a_guarded_route(): void
    {
        $this->actingAs($this->user())->post('/__drive-guarded')
            ->assertRedirect(route('google-drive'))->assertSessionHas('error', 'Connect your Google Drive before uploading files.');
    }

    public function test_a_json_request_without_drive_gets_409(): void
    {
        $this->actingAs($this->user())->postJson('/__drive-guarded')->assertStatus(409);
    }

    public function test_needs_reconnect_is_blocked_like_no_connection(): void
    {
        $user = $this->user();
        $this->connect($user, GoogleDriveConnection::NEEDS_RECONNECT);

        $this->actingAs($user)->post('/__drive-guarded')->assertRedirect(route('google-drive'));
        $this->assertFalse($user->fresh()->driveIsConnected());
    }

    public function test_a_connected_user_passes(): void
    {
        $user = $this->user();
        $this->connect($user, GoogleDriveConnection::CONNECTED);

        $this->actingAs($user)->post('/__drive-guarded')->assertOk()->assertSee('uploaded');
        $this->assertTrue($user->fresh()->driveIsConnected());
    }

    public function test_the_banner_shows_only_when_status_is_needs_reconnect(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('home'))->assertOk()->assertDontSee('Reconnect Google Drive');

        $this->connect($user, GoogleDriveConnection::NEEDS_RECONNECT);
        $this->actingAs($user->fresh())->get(route('home'))->assertOk()->assertSee('Reconnect Google Drive');
    }

    public function test_the_banner_is_not_shown_for_a_healthy_connection(): void
    {
        $user = $this->user();
        $this->connect($user, GoogleDriveConnection::CONNECTED);

        $this->actingAs($user)->get(route('home'))->assertOk()->assertDontSee('Reconnect Google Drive');
    }

    public function test_the_banner_shows_once_on_pages_that_do_not_use_the_profile_menu(): void
    {
        $user = $this->user();
        $this->connect($user, GoogleDriveConnection::NEEDS_RECONNECT);

        $this->actingAs($user->fresh())->get(route('procurement'))->assertOk()->assertSee('Reconnect Google Drive');

        $html = $this->actingAs($user->fresh())->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Reconnect Google Drive'));
    }

    public function test_the_banner_is_not_added_to_official_print_pages(): void
    {
        $user = $this->user();
        $this->connect($user, GoogleDriveConnection::NEEDS_RECONNECT);
        $aip = Aip::create(['organization_id' => $user->organization_id, 'school_id' => $user->school_id, 'fiscal_year' => 2026]);

        $this->actingAs($user->fresh())->get(route('aip.print', $aip))->assertOk()->assertDontSee('Reconnect Google Drive');
    }
}
