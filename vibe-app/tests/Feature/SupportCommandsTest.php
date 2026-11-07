<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Staff work the requests from the Telegram chat: take one, set its status, see the queue. */
class SupportCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555', 'services.telegram.webhook_secret' => 'hook-secret']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 900]])]);
    }

    private function ticket(string $subject = 'Printing', array $extra = []): SupportMessage
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 'k'.$n, 'slug' => 'k'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'K'.$n, 'name' => 'School '.$n, 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $school->id, 'type' => 'general', 'subject' => $subject, 'body' => 'Body', 'telegram_message_id' => 1000 + $n, 'telegram_status' => 'sent']);
    }

    private function say(string $text, array $extra = []): void
    {
        $from = ['id' => 11, 'first_name' => 'Anna', 'username' => 'anna'];
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 700, 'chat' => ['id' => 555], 'from' => $from, 'text' => $text] + $extra])->assertOk();
    }

    private function replyTo(SupportMessage $ticket): array
    {
        return ['reply_to_message' => ['message_id' => (int) $ticket->telegram_message_id]];
    }

    private function botSaid(string $needle): void
    {
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), $needle));
    }

    public function test_a_bare_assign_never_assigns_it_offers_the_staff_to_choose_from(): void
    {
        $ticket = $this->ticket();

        $this->say('/assign', $this->replyTo($ticket));

        $this->assertNull($ticket->fresh()->assigned_name);
        $this->botSaid('Assign '.$ticket->ticket_no.' to:');
    }

    public function test_assign_can_give_a_request_to_a_named_person_by_number(): void
    {
        $ticket = $this->ticket();

        $this->say('/assign '.$ticket->id.' @ben');

        $this->assertSame('@ben', $ticket->fresh()->assigned_name);
    }

    public function test_the_status_commands_set_the_status_by_reply_or_by_number_and_the_bot_confirms(): void
    {
        $byReply = $this->ticket('A');
        $byNumber = $this->ticket('B');

        $this->say('/process', $this->replyTo($byReply));
        $this->assertSame('for_process', $byReply->fresh()->status);
        $this->botSaid('On process');

        $this->say('/hold '.$byNumber->id);
        $this->assertSame('on_hold', $byNumber->fresh()->status);

        $this->say('/approve #'.$byNumber->id);
        $this->assertSame('approved', $byNumber->fresh()->status);

        $this->say('/resolve', $this->replyTo($byNumber));
        $this->assertSame('resolved', $byNumber->fresh()->status);
        $this->assertNotNull($byNumber->fresh()->resolved_at);
    }

    public function test_close_still_works_as_resolve(): void
    {
        $ticket = $this->ticket();

        $this->say('/close', $this->replyTo($ticket));

        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_a_resolved_request_cannot_be_moved_or_answered_from_telegram(): void
    {
        $ticket = $this->ticket('Done', ['status' => 'resolved', 'resolved_at' => now()]);

        $this->say('/hold', $this->replyTo($ticket));
        $this->say('Reopening answer', $this->replyTo($ticket));

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertSame(0, $ticket->replies()->count());
        $this->botSaid($ticket->ticket_no.' is resolved');
    }

    public function test_queue_and_mine_post_the_lists(): void
    {
        $free = $this->ticket('Free request');
        $this->ticket('Mine request', ['status' => 'for_process', 'assigned_name' => 'Anna', 'assigned_telegram_id' => 11]);

        $this->say('/queue');
        $this->botSaid('Free request');

        $this->say('/mine');
        $this->botSaid('Mine request');
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'Your requests') && ! str_contains((string) ($r['text'] ?? ''), 'Free request'));
        $this->assertSame('pending', $free->fresh()->status);
    }

    public function test_a_normal_reply_moves_pending_to_for_process_but_leaves_approved_alone(): void
    {
        $pending = $this->ticket('P');
        $approved = $this->ticket('Q', ['status' => 'approved']);

        $this->say('Please send the receipt', $this->replyTo($pending));
        $this->say('Thank you, noted', $this->replyTo($approved));

        $this->assertSame('for_process', $pending->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame(1, $approved->replies()->count());
        $this->assertSame('staff', $approved->replies()->first()->sender);
    }
}
