<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app used to run on UTC; it now runs on Philippine time, so every moment stored before the change (a date-and-time column) is moved forward 8 hours once.
 * A database with no user yet (a new install) has nothing to move, and the marker in system_settings keeps it from running twice.
 */
class ManilaTimeShift
{
    public const MARKER = 'times_shifted_to_manila';

    private const SKIP = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sqlite_sequence'];

    /** @return int how many columns were moved (0 when it did not run) */
    public static function run(): int
    {
        if (! Schema::hasTable('system_settings') || SystemSetting::get(self::MARKER) !== null) {
            return 0;
        }

        $moved = 0;
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            foreach (Schema::getTableListing(null, false) as $table) {
                if (in_array($table, self::SKIP, true)) {
                    continue;
                }
                foreach (Schema::getColumns($table) as $column) {
                    if (! in_array(strtolower((string) $column['type_name']), ['datetime', 'timestamp', 'timestamptz', 'datetimetz'], true)) {
                        continue;
                    }
                    DB::table($table)->whereNotNull($column['name'])->update([$column['name'] => DB::raw(self::plusEightHours($column['name']))]);
                    $moved++;
                }
            }
        }

        SystemSetting::put(self::MARKER, now()->toDateTimeString());

        return $moved;
    }

    private static function plusEightHours(string $column): string
    {
        $quoted = DB::getQueryGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => "DATE_ADD({$quoted}, INTERVAL 8 HOUR)",
            'pgsql' => "{$quoted} + interval '8 hours'",
            default => "datetime({$quoted}, '+8 hours')",
        };
    }
}
