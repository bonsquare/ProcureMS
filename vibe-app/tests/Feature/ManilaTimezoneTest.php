<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\ManilaTimeShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManilaTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_runs_on_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', now()->timezoneName);
        $this->assertSame('+08:00', now()->format('P'));
    }

    public function test_a_new_record_is_stamped_with_philippine_time(): void
    {
        $this->travelTo('2026-10-21 09:49:00');
        $user = User::factory()->create();
        $log = AuditLog::create(['user_id' => $user->id, 'action' => 'test']);

        $this->assertSame('2026-10-21 09:49:00', $log->fresh()->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 09:49:00', DB::table('audit_logs')->value('created_at'));
    }

    public function test_the_times_stored_on_utc_move_forward_eight_hours_once(): void
    {
        SystemSetting::query()->where('key', ManilaTimeShift::MARKER)->delete();
        $user = User::factory()->create();
        $id = DB::table('audit_logs')->insertGetId(['user_id' => $user->id, 'action' => 'old', 'created_at' => '2026-10-21 01:49:00', 'updated_at' => '2026-10-21 01:49:00']);
        DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => 'no-time', 'created_at' => null, 'updated_at' => null]);

        $this->assertGreaterThan(0, ManilaTimeShift::run());
        $this->assertSame('2026-10-21 09:49:00', DB::table('audit_logs')->where('id', $id)->value('created_at'));
        $this->assertNull(DB::table('audit_logs')->where('action', 'no-time')->value('created_at'));
        $this->assertNotNull(SystemSetting::get(ManilaTimeShift::MARKER));

        $this->assertSame(0, ManilaTimeShift::run(), 'it runs only once');
        $this->assertSame('2026-10-21 09:49:00', DB::table('audit_logs')->where('id', $id)->value('created_at'));
    }

    public function test_a_new_install_with_no_user_has_nothing_to_move(): void
    {
        DB::table('audit_logs')->delete();
        DB::table('users')->delete();
        SystemSetting::query()->where('key', ManilaTimeShift::MARKER)->delete();

        $this->assertSame(0, ManilaTimeShift::run());
        $this->assertNotNull(SystemSetting::get(ManilaTimeShift::MARKER));
    }
}
