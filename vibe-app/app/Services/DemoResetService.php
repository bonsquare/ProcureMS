<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/**
 * Puts the demo copy of ProcureMS back to its starting data. It only ever runs where DEMO_MODE is on, never on the live system.
 */
class DemoResetService
{
    public const SEEDERS = ['DatabaseSeeder', 'DemoShowcaseSeeder'];

    /** Wipes every table and loads the demo data again. */
    public function reset(): void
    {
        abort_unless(config('app.demo'), 403, 'Demo mode is off.');

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
    }

    /** Loads the demo data once, on a brand new demo database. Returns false when there was already data. */
    public function seedIfEmpty(): bool
    {
        if (User::withoutGlobalScopes()->exists()) {
            return false;
        }
        $this->seed();

        return true;
    }

    private function seed(): void
    {
        foreach (self::SEEDERS as $seeder) {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\'.$seeder, '--force' => true]);
        }
    }
}
