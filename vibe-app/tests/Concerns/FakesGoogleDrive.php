<?php

namespace Tests\Concerns;

use App\Models\GoogleDriveConnection;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** A fake Google (OAuth and Drive) for feature tests, plus ready-made users and connections. */
trait FakesGoogleDrive
{
    private int $folderCounter = 0;

    /** @var list<int> Statuses the next upload calls answer with before succeeding. */
    private array $uploadStatuses = [];

    private ?array $tokenFailure = null;

    private function user(string $code = 'A', string $role = 'school_admin'): User
    {
        $organization = Organization::create(['name' => "Org {$code}", 'slug' => "org-{$code}", 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => "School {$code}", 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function fakeGoogle(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                if ($this->tokenFailure) {
                    return Http::response($this->tokenFailure, 400);
                }

                return Http::response(($request['grant_type'] ?? '') === 'refresh_token'
                    ? ['access_token' => 'ya29.refreshed', 'expires_in' => 3600]
                    : ['access_token' => 'ya29.first-token', 'refresh_token' => '1//refresh-token', 'expires_in' => 3600]);
            }
            if (str_contains($url, 'drive/v3/about')) {
                return Http::response(['user' => ['emailAddress' => 'owner@gmail.com']]);
            }
            if (str_contains($url, '/upload/drive/v3/files')) {
                $status = array_shift($this->uploadStatuses);

                return $status ? Http::response(['error' => ['code' => $status]], $status) : Http::response(['id' => 'file-1', 'size' => '12']);
            }
            if (str_contains($url, 'alt=media')) {
                return Http::response('SQLITE-BYTES');
            }
            if (str_contains($url, 'drive/v3/files') && $request->method() === 'GET') {
                return Http::response(['files' => []]);
            }
            if (str_contains($url, 'drive/v3/files') && $request->method() === 'POST') {
                return Http::response(['id' => 'folder-'.++$this->folderCounter]);
            }

            return Http::response([], 204);
        });
    }

    private function connection(User $user, array $overrides = []): GoogleDriveConnection
    {
        return GoogleDriveConnection::create($overrides + [
            'user_id' => $user->id, 'google_email' => 'owner@gmail.com', 'access_token' => 'ya29.old', 'refresh_token' => '1//refresh-token',
            'expires_at' => now()->addHour(), 'root_folder_id' => 'root-1',
            'folder_ids' => ['Backup' => 'b-1', 'Logo' => 'l-1', 'Files' => 'f-1'], 'status' => 'connected', 'connected_at' => now(),
        ]);
    }
}
