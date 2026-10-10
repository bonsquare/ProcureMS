<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/** The real VACUUM INTO copy, which SQLite refuses to run inside a transaction, so this test commits the wrapper transaction first and cleans up after itself. */
class DatabaseBackupCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_copy_is_a_consistent_sqlite_file_with_the_current_data(): void
    {
        DB::commit(); // leave the transaction RefreshDatabase opened: SQLite will not VACUUM inside one
        $user = User::factory()->create(['email' => 'copy-check@example.com']);

        try {
            $path = app(DatabaseBackupService::class)->copyToTempFile();

            $this->assertFileExists($path);
            $copy = new PDO('sqlite:'.$path);
            $this->assertSame(1, (int) $copy->query("select count(*) from users where email = 'copy-check@example.com'")->fetchColumn());
            $copy = null;
            unlink($path);
        } finally {
            $user->delete();
        }
    }
}
