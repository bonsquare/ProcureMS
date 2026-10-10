<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Illuminate\Console\Command;

class ResetDemo extends Command
{
    protected $signature = 'demo:reset {--if-empty : Only load the demo data when the database has no users} {--force : Do not ask for confirmation}';

    protected $description = 'Wipe the demo database and load the demo data again (only when DEMO_MODE is on)';

    public function handle(DemoResetService $demo): int
    {
        if (! config('app.demo')) {
            $this->error('Demo mode is off (DEMO_MODE). Nothing was changed.');

            return self::FAILURE;
        }

        if ($this->option('if-empty')) {
            $this->info($demo->seedIfEmpty() ? 'Demo data loaded.' : 'The database already has data. Left as it is.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('This erases ALL data in this database and loads the demo data. Continue?')) {
            return self::FAILURE;
        }

        $demo->reset();
        $this->info('Demo data reset.');

        return self::SUCCESS;
    }
}
