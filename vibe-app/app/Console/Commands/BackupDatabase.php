<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Back up the whole database to the master user\'s Google Drive (runs every midnight, Philippine time)';

    public function handle(DatabaseBackupService $backups): int
    {
        $run = $backups->run('automatic');

        if ($run->status === BackupRun::SUCCESS) {
            $this->info("Backup saved to Google Drive as {$run->file_name}.");

            return self::SUCCESS;
        }
        $this->error("Backup failed: {$run->error}");

        return self::FAILURE;
    }
}
