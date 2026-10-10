<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Copies the whole SQLite database to the master user's Google Drive (ProcMS/Backup). The file holds every
 * school's data, so it is only ever handled for the master user.
 */
class DatabaseBackupService
{
    /** The upload is built in memory (php.ini allows 256 MB), so larger files fail cleanly instead of crashing. */
    public const MAX_UPLOAD_BYTES = 40 * 1024 * 1024;

    public function __construct(private GoogleDriveService $drive, private ?string $driverOverride = null) {}

    /** Never throws: a failed run is stored with its error so it shows in the history. */
    public function run(string $type, ?User $by = null): BackupRun
    {
        $master = $by ?? User::withoutGlobalScopes()->where('role', 'master_user')->orderBy('id')->first();

        try {
            $connection = $master?->driveConnection;
            if (! $connection?->isConnected()) {
                throw new RuntimeException('The master user has not connected Google Drive.');
            }
            $path = $this->copyToTempFile();
            try {
                if (filesize($path) > self::MAX_UPLOAD_BYTES) {
                    throw new RuntimeException('The database is too large ('.round(filesize($path) / 1048576).' MB) for the simple Google Drive upload. Ask the developer to enable resumable uploads.');
                }
                $name = 'procms-'.now()->format('Y-m-d-Hi').'.sqlite';
                $stored = $this->drive->upload($connection, 'Backup', $name, (string) file_get_contents($path), 'application/vnd.sqlite3');
            } finally {
                @unlink($path);
            }

            $run = BackupRun::create([
                'type' => $type, 'status' => BackupRun::SUCCESS, 'size' => $stored['size'],
                'drive_file_id' => $stored['id'], 'file_name' => $name, 'created_by' => $master->id,
            ]);
        } catch (Throwable $exception) {
            return BackupRun::create([
                'type' => $type, 'status' => BackupRun::FAILED, 'error' => Str::limit($exception->getMessage(), 500, ''), 'created_by' => $master?->id,
            ]);
        }

        if ($type === 'automatic') {
            $this->prune();
        }

        return $run;
    }

    /** A consistent copy of the database in a temporary file; the caller deletes it. */
    public function copyToTempFile(): string
    {
        if (($this->driverOverride ?? DB::connection()->getDriverName()) !== 'sqlite') {
            throw new RuntimeException('Database backup works with SQLite only.');
        }
        $directory = storage_path('app/backup-tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.DIRECTORY_SEPARATOR.'backup-'.Str::random(16).'.sqlite';
        DB::statement('VACUUM INTO ?', [$path]);

        return $path;
    }

    /** Deletes automatic backups beyond the newest $keep from Drive (their history rows stay). Returns how many were removed. */
    public function prune(int $keep = 30): int
    {
        $old = BackupRun::where('type', 'automatic')->where('status', BackupRun::SUCCESS)->whereNotNull('drive_file_id')
            ->orderByDesc('created_at')->orderByDesc('id')->get()->slice($keep);
        $connection = User::withoutGlobalScopes()->where('role', 'master_user')->orderBy('id')->first()?->driveConnection;
        if ($old->isEmpty() || ! $connection?->isConnected()) {
            return 0;
        }

        $removed = 0;
        foreach ($old as $run) {
            try {
                $this->drive->delete($connection, $run->drive_file_id);
                $run->update(['drive_file_id' => null]);
                $removed++;
            } catch (Throwable) {
                // Left for the next run.
            }
        }

        return $removed;
    }
}
