<?php

namespace Tests\Feature;

use App\Exceptions\DriveNotConnected;
use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesGoogleDrive;
use Tests\TestCase;

class GoogleDriveConnectionTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => [
            'client_id' => 'client-id-123', 'client_secret' => 'client-secret-456',
            'redirect' => 'https://procms.example/google-drive/callback',
        ]]);
    }

    public function test_the_connect_page_offers_google_sign_in(): void
    {
        $this->actingAs($this->user())->get(route('google-drive'))->assertOk()->assertSee('Connect Google Drive');
    }

    public function test_redirect_sends_the_user_to_google_with_the_drive_file_scope_and_offline_access(): void
    {
        $response = $this->actingAs($this->user())->get(route('google-drive.redirect'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('client-id-123', $query['client_id']);
        $this->assertSame('https://procms.example/google-drive/callback', $query['redirect_uri']);
        $this->assertSame(session('google_drive_state'), $query['state']);
    }

    public function test_callback_with_a_wrong_state_is_refused(): void
    {
        $this->fakeGoogle();
        $user = $this->user();

        $this->actingAs($user)->withSession(['google_drive_state' => 'right'])
            ->get(route('google-drive.callback', ['state' => 'wrong', 'code' => 'abc']))
            ->assertRedirect(route('google-drive'))->assertSessionHas('error');

        $this->assertSame(0, GoogleDriveConnection::count());
    }

    public function test_callback_stores_encrypted_tokens_and_creates_the_four_folders(): void
    {
        $this->fakeGoogle();
        $user = $this->user();

        $this->actingAs($user)->withSession(['google_drive_state' => 'right'])
            ->get(route('google-drive.callback', ['state' => 'right', 'code' => 'abc']))
            ->assertRedirect(route('google-drive'))->assertSessionHas('success');

        $connection = GoogleDriveConnection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('connected', $connection->status);
        $this->assertSame('owner@gmail.com', $connection->google_email);
        $this->assertSame('ya29.first-token', $connection->access_token);
        $this->assertNotSame('ya29.first-token', DB::table('google_drive_connections')->value('access_token'));
        $this->assertNotSame('1//refresh-token', DB::table('google_drive_connections')->value('refresh_token'));
        $this->assertNotEmpty($connection->root_folder_id);
        $this->assertEqualsCanonicalizing(['Backup', 'Logo', 'Files'], array_keys($connection->folder_ids));
    }

    public function test_cancel_on_the_google_screen_creates_no_connection(): void
    {
        $this->fakeGoogle();

        $this->actingAs($this->user())->withSession(['google_drive_state' => 'right'])
            ->get(route('google-drive.callback', ['state' => 'right', 'error' => 'access_denied']))
            ->assertRedirect(route('google-drive'))->assertSessionHas('error');

        $this->assertSame(0, GoogleDriveConnection::count());
    }

    public function test_an_expired_token_is_refreshed(): void
    {
        $this->fakeGoogle();
        $connection = $this->connection($this->user(), ['expires_at' => now()->subMinute()]);

        $token = app(GoogleDriveService::class)->accessToken($connection);

        $this->assertSame('ya29.refreshed', $token);
        $this->assertTrue($connection->fresh()->expires_at->isFuture());
    }

    public function test_invalid_grant_marks_the_connection_needs_reconnect(): void
    {
        $this->fakeGoogle();
        $this->tokenFailure = ['error' => 'invalid_grant'];
        $connection = $this->connection($this->user(), ['expires_at' => now()->subMinute()]);

        try {
            app(GoogleDriveService::class)->accessToken($connection);
            $this->fail('A revoked token must raise DriveNotConnected.');
        } catch (DriveNotConnected) {
            $this->assertSame('needs_reconnect', $connection->fresh()->status);
        }
    }

    public function test_a_deleted_folder_is_recreated_on_upload(): void
    {
        $this->fakeGoogle();
        $this->uploadStatuses = [404];
        $connection = $this->connection($this->user());

        $result = app(GoogleDriveService::class)->upload($connection, 'Files', 'note.txt', 'hello', 'text/plain');

        $this->assertSame('file-1', $result['id']);
        $this->assertNotSame('f-1', $connection->fresh()->folder_ids['Files']);
    }

    public function test_a_user_only_disconnects_their_own_connection(): void
    {
        $this->fakeGoogle();
        $a = $this->user('A');
        $b = $this->user('B');
        $this->connection($a);
        $this->connection($b);

        $this->actingAs($b)->delete(route('google-drive.disconnect'))->assertRedirect(route('google-drive'));

        $this->assertNull(GoogleDriveConnection::where('user_id', $b->id)->first());
        $this->assertNotNull(GoogleDriveConnection::where('user_id', $a->id)->first());
    }

    private function saveSchoolLogo(User $user)
    {
        return $this->actingAs($user)->post(route('school-settings.school'), [
            'school_id' => $user->school_id, 'name' => 'School A',
            'school_logo' => UploadedFile::fake()->create('crest.png', 4, 'image/png'),
        ]);
    }

    public function test_a_saved_logo_is_copied_to_the_logo_folder_when_connected(): void
    {
        Storage::fake('public');
        $this->fakeGoogle();
        $user = $this->user();
        $this->connection($user);

        $this->saveSchoolLogo($user)->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files') && str_contains($r->body(), '"parents":["l-1"]') && str_contains($r->body(), '"name":"crest.png"'));
    }

    public function test_a_logo_still_saves_when_drive_is_not_connected(): void
    {
        Storage::fake('public');
        $this->fakeGoogle();
        $user = $this->user();

        $this->saveSchoolLogo($user)->assertSessionHasNoErrors();

        $this->assertNotNull($user->school->fresh()->logo_path);
        Http::assertNothingSent();
    }

    public function test_a_logo_still_saves_when_drive_fails(): void
    {
        Storage::fake('public');
        $this->fakeGoogle();
        $this->uploadStatuses = [500];
        $user = $this->user();
        $this->connection($user);

        $this->saveSchoolLogo($user)->assertSessionHasNoErrors();

        $this->assertNotNull($user->school->fresh()->logo_path);
    }
}
