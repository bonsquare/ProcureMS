<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveNotConnected;
use App\Models\BackupDownload;
use App\Models\BackupRun;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeMaster($request);

        return response()->view('backup', [
            'runs' => BackupRun::with(['creator', 'downloads.user'])->latest()->latest('id')->limit(100)->get(),
            'connection' => $request->user()->driveConnection,
        ]);
    }

    public function run(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        $this->authorizeMaster($request);
        $run = $backups->run('manual', $request->user());

        return redirect()->route('backup.index')->with(
            $run->status === BackupRun::SUCCESS ? 'success' : 'error',
            $run->status === BackupRun::SUCCESS ? "Backup saved to your Google Drive as {$run->file_name}." : "The backup failed: {$run->error}",
        );
    }

    public function download(Request $request, GoogleDriveService $drive, int $backupRun)
    {
        $this->authorizeMaster($request);
        $run = BackupRun::findOrFail($backupRun);
        abort_unless($run->isDownloadable(), 404);

        // The file lives in the Drive of the master who made the backup (the first master for automatic ones).
        $connection = $run->creator?->driveConnection ?? $request->user()->driveConnection;
        if (! $connection?->isConnected()) {
            return redirect()->route('backup.index')->with('error', 'Connect the Google Drive that holds this backup, then try again.');
        }

        try {
            $contents = $drive->download($connection, $run->drive_file_id);
        } catch (DriveNotConnected $exception) {
            return redirect()->route('backup.index')->with('error', $exception->getMessage());
        }
        BackupDownload::create(['backup_run_id' => $run->id, 'user_id' => $request->user()->id]);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.sqlite3',
            'Content-Disposition' => 'attachment; filename="'.$run->file_name.'"',
        ]);
    }

    private function authorizeMaster(Request $request): void
    {
        abort_unless($request->user()?->hasAccess('backup'), 403);
    }
}
