<?php

namespace App\Services;

use App\Exceptions\DriveNotConnected;
use App\Models\GoogleDriveConnection;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to Google with the Laravel HTTP client (no Google package). Each user has one connection, and the
 * app only ever sees the folders and files it created itself (the drive.file scope).
 */
class GoogleDriveService
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public const ROOT_FOLDER = 'ProcMS';

    public const FOLDERS = ['Backup', 'Logo', 'Files'];

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    public function authorizationUrl(User $user): string
    {
        $state = Str::random(40);
        session()->put('google_drive_state', $state);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(User $user, string $code): GoogleDriveConnection
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new DriveNotConnected('Google did not accept the sign-in. Please try again.');
        }

        $existing = GoogleDriveConnection::where('user_id', $user->id)->first();
        $token = $response->json('access_token');
        $about = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user(emailAddress)']);

        $connection = GoogleDriveConnection::updateOrCreate(['user_id' => $user->id], [
            'google_email' => $about->json('user.emailAddress'),
            'access_token' => $token,
            'refresh_token' => $response->json('refresh_token') ?: $existing?->refresh_token,
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            'status' => GoogleDriveConnection::CONNECTED,
            'connected_at' => now(),
        ]);
        $this->ensureFolders($connection);

        return $connection->fresh();
    }

    public function accessToken(GoogleDriveConnection $connection): string
    {
        if (! $connection->isConnected()) {
            throw DriveNotConnected::reconnect();
        }
        if ($connection->expires_at && $connection->expires_at->gt(now()->addMinute())) {
            return $connection->access_token;
        }
        if (! $connection->refresh_token) {
            $this->markNeedsReconnect($connection);
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            if ($response->json('error') === 'invalid_grant' || $response->status() === 401) {
                $this->markNeedsReconnect($connection);
            }
            throw new DriveNotConnected('Google could not renew your Drive access right now. Try again in a moment.');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $connection->access_token;
    }

    /** @return array{root: string, Backup: string, Logo: string, Files: string} */
    public function ensureFolders(GoogleDriveConnection $connection): array
    {
        $token = $this->accessToken($connection);
        $root = $connection->root_folder_id ?: $this->findOrCreateFolder($token, self::ROOT_FOLDER, null);
        $folders = $connection->folder_ids ?? [];
        foreach (self::FOLDERS as $name) {
            $folders[$name] ??= $this->findOrCreateFolder($token, $name, $root);
        }
        $connection->update(['root_folder_id' => $root, 'folder_ids' => $folders]);

        return ['root' => $root] + array_intersect_key($folders, array_flip(self::FOLDERS));
    }

    /** @return array{id: string, size: int} */
    public function upload(GoogleDriveConnection $connection, string $folder, string $name, string $contents, string $mime): array
    {
        $response = $this->sendUpload($connection, $folder, $name, $contents, $mime);
        if ($response->status() === 404) {
            // The user deleted the folder in Drive: build the folders again and retry once.
            $connection->update(['root_folder_id' => null, 'folder_ids' => null]);
            $response = $this->sendUpload($connection->fresh(), $folder, $name, $contents, $mime);
        }
        $this->assertOk($connection, $response);

        return ['id' => (string) $response->json('id'), 'size' => (int) ($response->json('size') ?? strlen($contents))];
    }

    public function download(GoogleDriveConnection $connection, string $fileId): string
    {
        $response = $this->api($this->accessToken($connection))->get('https://www.googleapis.com/drive/v3/files/'.$fileId, ['alt' => 'media']);
        $this->assertOk($connection, $response);

        return $response->body();
    }

    public function delete(GoogleDriveConnection $connection, string $fileId): void
    {
        $response = $this->api($this->accessToken($connection))->delete('https://www.googleapis.com/drive/v3/files/'.$fileId);
        if ($response->status() !== 404) {
            $this->assertOk($connection, $response);
        }
    }

    public function webLink(string $fileId): string
    {
        return 'https://drive.google.com/file/d/'.$fileId.'/view';
    }

    private function sendUpload(GoogleDriveConnection $connection, string $folder, string $name, string $contents, string $mime): Response
    {
        $parent = $this->ensureFolders($connection)[$folder];
        $boundary = 'procms'.Str::random(24);
        $metadata = json_encode(['name' => $name, 'parents' => [$parent]]);
        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
            ."--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n{$contents}\r\n--{$boundary}--";

        return $this->api($this->accessToken($connection))
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,size');
    }

    private function findOrCreateFolder(string $token, string $name, ?string $parent): string
    {
        $query = "name = '{$name}' and mimeType = '".self::FOLDER_MIME."' and trashed = false".($parent ? " and '{$parent}' in parents" : '');
        $found = $this->api($token)->get('https://www.googleapis.com/drive/v3/files', ['q' => $query, 'fields' => 'files(id)', 'pageSize' => 1]);
        if ($found->successful() && $found->json('files.0.id')) {
            return $found->json('files.0.id');
        }
        $created = $this->api($token)->post('https://www.googleapis.com/drive/v3/files?fields=id', array_filter([
            'name' => $name, 'mimeType' => self::FOLDER_MIME, 'parents' => $parent ? [$parent] : null,
        ]));
        if (! $created->successful() || ! $created->json('id')) {
            throw new DriveNotConnected('Google Drive could not create the ProcMS folders. Please try again.');
        }

        return $created->json('id');
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(60);
    }

    private function assertOk(GoogleDriveConnection $connection, Response $response): void
    {
        if ($response->status() === 401) {
            $this->markNeedsReconnect($connection);
        }
        if (! $response->successful()) {
            throw new DriveNotConnected('Google Drive did not accept the request ('.$response->status().'). Please try again.');
        }
    }

    private function markNeedsReconnect(GoogleDriveConnection $connection): never
    {
        $connection->update(['status' => GoogleDriveConnection::NEEDS_RECONNECT]);

        throw DriveNotConnected::reconnect();
    }
}
