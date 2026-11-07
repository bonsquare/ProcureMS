<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A resolved request is removed from the system five days after it was resolved. */
class SupportPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(string $status, $resolvedAt = null): SupportMessage
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 'u'.$n, 'slug' => 'u'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'U'.$n, 'name' => 'School '.$n, 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $ticket = SupportMessage::create(['user_id' => $user->id, 'school_id' => $school->id, 'type' => 'general', 'subject' => 'S'.$n, 'body' => 'B', 'status' => $status, 'resolved_at' => $resolvedAt]);
        $reply = $ticket->replies()->create(['body' => 'Answer', 'sender' => 'staff']);
        SupportAttachment::create(['support_message_id' => $ticket->id, 'support_reply_id' => $reply->id, 'user_id' => $user->id, 'drive_file_id' => 'f'.$n, 'name' => 'x.png', 'size' => 1]);

        return $ticket;
    }

    public function test_a_request_resolved_five_days_ago_is_deleted_with_its_replies_and_attachment_rows(): void
    {
        Http::fake();
        $old = $this->ticket('resolved', now()->subDays(5)->subMinute());

        $this->artisan('support:purge')->assertSuccessful();

        $this->assertNull(SupportMessage::find($old->id));
        $this->assertSame(0, $old->replies()->count());
        $this->assertSame(0, SupportAttachment::count());
        $this->assertSame(1, AuditLog::where('action', 'support_requests_purged')->count());
        Http::assertNothingSent();
    }

    public function test_one_resolved_four_days_and_23_hours_ago_stays(): void
    {
        $young = $this->ticket('resolved', now()->subDays(4)->subHours(23));

        $this->artisan('support:purge')->assertSuccessful();

        $this->assertNotNull(SupportMessage::find($young->id));
        $this->assertSame(1, SupportAttachment::count());
    }

    public function test_open_requests_are_never_purged_however_old(): void
    {
        $open = $this->ticket('for_process');
        $open->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->artisan('support:purge')->assertSuccessful();

        $this->assertNotNull(SupportMessage::find($open->id));
    }

    public function test_the_purge_runs_every_day(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('support:purge')->assertSuccessful();
    }
}
