<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A failed nightly backup is sent to the Telegram chat, so nobody has to open the dashboard to find out. */
class BackupFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    private function telegram(bool $on): void
    {
        config(['services.telegram.token' => $on ? 'T' : null, 'services.telegram.chat_id' => $on ? '42' : null]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    public function test_a_failed_backup_is_sent_to_telegram(): void
    {
        $this->telegram(true);

        $this->artisan('backup:database')->assertFailed();

        $this->assertSame(1, BackupRun::where('status', BackupRun::FAILED)->count());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMessage') && str_contains($r->body(), 'backup failed'));
    }

    public function test_nothing_is_sent_when_telegram_is_not_set_up(): void
    {
        $this->telegram(false);

        $this->artisan('backup:database')->assertFailed();

        Http::assertNothingSent();
    }
}
