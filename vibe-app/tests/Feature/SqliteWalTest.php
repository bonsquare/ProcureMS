<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The live database is one SQLite file shared by every user: it must let readers and writers work at the same time. */
class SqliteWalTest extends TestCase
{
    public function test_the_sqlite_connection_uses_wal_with_a_busy_timeout(): void
    {
        $config = config('database.connections.sqlite');

        $this->assertSame('WAL', $config['journal_mode']);
        $this->assertSame(5000, $config['busy_timeout']);
        $this->assertSame('NORMAL', $config['synchronous']);
    }

    public function test_a_file_database_opens_in_wal_mode(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wal');
        $connection = app('db.factory')->make(['database' => $file] + config('database.connections.sqlite'), 'wal-check');

        $this->assertSame('wal', strtolower($connection->selectOne('PRAGMA journal_mode')->journal_mode));

        $connection->disconnect();
        foreach ([$file, $file.'-wal', $file.'-shm'] as $path) {
            @unlink($path);
        }
    }
}
