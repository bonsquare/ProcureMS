<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A ticket number carries the date and time the request was made; the status starts at Pending and a staff answer puts it On process. */
class SupportTicketNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555', 'services.telegram.webhook_secret' => 'hook-secret']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 900]])]);
        Carbon::setTestNow(Carbon::parse('2026-11-07 17:45:30'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 'tn'.$n, 'slug' => 'tn'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'TN'.$n, 'name' => 'Lubas Elementary School', 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function ticket(array $extra = []): SupportMessage
    {
        $user = $this->user();

        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $user->school_id, 'type' => 'general', 'subject' => 'For Update', 'body' => 'Problem with the system printing', 'telegram_message_id' => 61, 'telegram_status' => 'sent']);
    }

    private function say(string $text, array $extra = []): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 700, 'chat' => ['id' => 555], 'from' => ['id' => 11, 'first_name' => 'Anna'], 'text' => $text] + $extra])->assertOk();
    }

    public function test_the_ticket_number_has_the_date_the_time_and_the_request_number(): void
    {
        $ticket = $this->ticket();

        $this->assertSame('SUP-20261107-1745-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT), $ticket->ticket_no);
        $this->assertSame('Nov 07, 2026 5:45 PM', $ticket->filed_at);
    }

    public function test_a_new_request_message_in_telegram_shows_the_ticket_number_the_date_and_time_and_pending(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'For Update', 'body' => 'Problem with the system printing'])->assertSessionHasNoErrors();

        $ticket = SupportMessage::first();
        Http::assertSent(fn ($r) => str_contains((string) $r['text'], "New Support Request\n🎫 ".$ticket->ticket_no)
            && str_contains((string) $r['text'], 'Subject: For Update') && str_contains((string) $r['text'], 'Status: Pending')
            && str_contains((string) $r['text'], 'Filed: Nov 07, 2026 5:45 PM') && str_contains((string) $r['text'], 'Issue')
            && str_contains((string) $r['text'], '/close '.$ticket->id) && ! str_contains((string) $r['text'], 'Support #'));
    }

    public function test_commands_accept_the_short_number_and_the_full_ticket_number(): void
    {
        $byFull = $this->ticket();
        $byShort = $this->ticket();

        $this->say('/hold '.$byFull->ticket_no);
        $this->say('/hold #'.$byShort->id);

        $this->assertSame('on_hold', $byFull->fresh()->status);
        $this->assertSame('on_hold', $byShort->fresh()->status);
    }

    public function test_an_answer_can_start_with_the_ticket_number(): void
    {
        $ticket = $this->ticket();

        $this->say($ticket->ticket_no.' Please send a new screenshot');

        $this->assertSame('Please send a new screenshot', $ticket->replies()->first()->body);
        $this->assertSame('for_process', $ticket->fresh()->status);
    }

    public function test_the_bot_and_the_queue_use_the_ticket_number_and_on_process(): void
    {
        $ticket = $this->ticket();

        $this->say('/process', ['reply_to_message' => ['message_id' => 61]]);
        Http::assertSent(fn ($r) => str_contains((string) $r['text'], $ticket->ticket_no.' · On process'));

        $this->say('/queue');
        Http::assertSent(fn ($r) => str_contains((string) $r['text'], 'On process:') && str_contains((string) $r['text'], $ticket->ticket_no));
    }

    public function test_the_page_shows_the_ticket_number_with_the_date_and_time(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($ticket->user)->get(route('support.show', $ticket))->assertOk()->assertSee($ticket->ticket_no)->assertSee('Nov 07, 2026 5:45 PM');
    }
}
