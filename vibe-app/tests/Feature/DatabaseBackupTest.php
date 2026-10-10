<?php

namespace Tests\Feature;

use App\Models\BackupDownload;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\FakesGoogleDrive;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'https://procms.example/google-drive/callback']]);
        // VACUUM cannot run inside the transaction RefreshDatabase wraps each test in, so the copy is faked here
        // and covered for real in DatabaseBackupCopyTest.
        $mock = Mockery::mock(DatabaseBackupService::class, [app(GoogleDriveService::class)])->makePartial();
        $mock->shouldReceive('copyToTempFile')->andReturnUsing(function () {
            $path = tempnam(sys_get_temp_dir(), 'bk');
            file_put_contents($path, 'SQLITE-BYTES');

            return $path;
        });
        $this->instance(DatabaseBackupService::class, $mock);
    }

    private function master(bool $connected = true): User
    {
        $master = User::factory()->create(['role' => 'master_user', 'name' => 'Boss Master']);
        if ($connected) {
            $this->connection($master);
        }

        return $master;
    }

    private function makeRun(array $attributes = []): BackupRun
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);
        $run = BackupRun::create($attributes + ['type' => 'manual', 'status' => 'success', 'size' => 12, 'drive_file_id' => 'drive-7', 'file_name' => 'procms-2026-10-10-0000.sqlite']);
        if ($createdAt) {
            $run->forceFill(['created_at' => $createdAt])->save();
        }

        return $run;
    }

    public function test_only_the_master_user_can_open_run_and_download_backups(): void
    {
        $this->fakeGoogle();
        $admin = $this->user();
        $this->connection($admin);
        $run = $this->makeRun();

        $this->actingAs($admin)->get(route('backup.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('backup.run'))->assertForbidden();
        $this->actingAs($admin)->get(route('backup.download', $run))->assertForbidden();
        $this->assertSame(1, BackupRun::count());
    }

    public function test_a_manual_backup_is_uploaded_to_the_backup_folder_and_recorded(): void
    {
        $this->fakeGoogle();
        $master = $this->master();

        $this->actingAs($master)->post(route('backup.run'))->assertRedirect(route('backup.index'))->assertSessionHas('success');

        $run = BackupRun::firstOrFail();
        $this->assertSame(['manual', 'success', 'file-1', $master->id], [$run->type, $run->status, $run->drive_file_id, $run->created_by]);
        $this->assertMatchesRegularExpression('/^procms-\d{4}-\d{2}-\d{2}-\d{4}\.sqlite$/', $run->file_name);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files') && str_contains($r->body(), '"parents":["b-1"]') && str_contains($r->body(), 'SQLITE-BYTES'));
    }

    public function test_downloading_logs_who_and_when_and_shows_in_the_history(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $run = $this->makeRun();

        $this->actingAs($master)->get(route('backup.download', $run))->assertOk()->assertSee('SQLITE-BYTES');

        $this->assertSame(1, BackupDownload::where(['backup_run_id' => $run->id, 'user_id' => $master->id])->count());
        $this->actingAs($master)->get(route('backup.index'))->assertOk()->assertSee('Boss Master')->assertSee($run->file_name);
    }

    public function test_a_failed_run_cannot_be_downloaded(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $failed = $this->makeRun(['status' => 'failed', 'drive_file_id' => null, 'error' => 'boom']);

        $this->actingAs($master)->get(route('backup.download', $failed))->assertNotFound();
    }

    public function test_a_failed_upload_is_recorded_not_thrown(): void
    {
        $this->fakeGoogle();
        $this->uploadStatuses = [500];
        $master = $this->master();

        $this->actingAs($master)->post(route('backup.run'))->assertRedirect(route('backup.index'))->assertSessionHas('error');

        $run = BackupRun::firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertNull($run->drive_file_id);
        $this->assertNotEmpty($run->error);
        $this->actingAs($master)->get(route('backup.index'))->assertOk()->assertSee('Failed');
    }

    public function test_a_master_without_drive_records_a_failed_run(): void
    {
        $this->fakeGoogle();
        $master = $this->master(false);

        $run = app(DatabaseBackupService::class)->run('manual', $master);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Google Drive', $run->error);
        Http::assertNothingSent();
    }

    public function test_prune_keeps_the_latest_30_automatic_backups(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        for ($i = 1; $i <= 32; $i++) {
            $this->makeRun(['type' => 'automatic', 'drive_file_id' => "drive-{$i}", 'created_at' => now()->subDays(40 - $i)]);
        }
        $manual = $this->makeRun(['type' => 'manual', 'drive_file_id' => 'manual-keep', 'created_at' => now()->subYear()]);

        $removed = app(DatabaseBackupService::class)->prune(30);

        $this->assertSame(2, $removed);
        $this->assertSame(30, BackupRun::where('type', 'automatic')->whereNotNull('drive_file_id')->count());
        $this->assertSame('manual-keep', $manual->fresh()->drive_file_id);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/drive/v3/files/drive-1'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/drive/v3/files/drive-2'));
        $this->assertNotNull($master);
    }

    public function test_a_non_sqlite_connection_fails_cleanly(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $this->app->forgetInstance(DatabaseBackupService::class);
        $this->app->bind(DatabaseBackupService::class, fn ($app) => new DatabaseBackupService($app->make(GoogleDriveService::class), 'mysql'));

        $run = app(DatabaseBackupService::class)->run('manual', $master);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('SQLite', $run->error);
        Http::assertNothingSent();
    }

    public function test_the_master_dashboard_warns_about_a_failed_backup(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $admin = $this->user();
        $this->makeRun(['status' => 'failed', 'drive_file_id' => null, 'error' => 'Drive said no']);

        $this->actingAs($master)->get(route('home'))->assertOk()->assertSee('The last database backup failed');
        $this->actingAs($admin)->get(route('home'))->assertOk()->assertDontSee('The last database backup failed');
    }

    public function test_a_later_success_clears_the_dashboard_warning(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $this->makeRun(['status' => 'failed', 'drive_file_id' => null, 'error' => 'x', 'created_at' => now()->subHour()]);
        $this->makeRun(['status' => 'success', 'created_at' => now()]);

        $this->actingAs($master)->get(route('home'))->assertOk()->assertDontSee('The last database backup failed');
    }

    public function test_download_without_a_drive_connection_redirects_with_a_message(): void
    {
        $this->fakeGoogle();
        $master = $this->master(false);
        $run = $this->makeRun();

        $this->actingAs($master)->get(route('backup.download', $run))
            ->assertRedirect(route('backup.index'))->assertSessionHas('error');

        $this->assertSame(0, BackupDownload::count());
    }

    public function test_a_second_master_downloads_through_the_drive_of_the_master_who_made_the_backup(): void
    {
        $this->fakeGoogle();
        $first = $this->master();
        $second = User::factory()->create(['role' => 'master_user', 'name' => 'Second Master']);
        $run = $this->makeRun(['type' => 'automatic', 'created_by' => $first->id]);

        $this->actingAs($second)->get(route('backup.download', $run))->assertOk()->assertSee('SQLITE-BYTES');

        $this->assertSame(1, BackupDownload::where(['backup_run_id' => $run->id, 'user_id' => $second->id])->count());
    }

    public function test_a_database_too_large_for_the_simple_upload_is_recorded_as_failed(): void
    {
        $this->fakeGoogle();
        $master = $this->master();
        $big = tempnam(sys_get_temp_dir(), 'bigbk');
        $handle = fopen($big, 'wb');
        ftruncate($handle, 41 * 1024 * 1024);
        fclose($handle);
        $mock = Mockery::mock(DatabaseBackupService::class, [app(GoogleDriveService::class)])->makePartial();
        $mock->shouldReceive('copyToTempFile')->andReturn($big);
        $this->instance(DatabaseBackupService::class, $mock);

        $run = app(DatabaseBackupService::class)->run('manual', $master);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('too large', $run->error);
        $this->assertFileDoesNotExist($big);
        Http::assertNothingSent();
    }
}
