<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** When a request is taken the bot opens a topic for it in the group; the conversation lives there and the topic closes with the request. */
class SupportTopicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '-1004457287957', 'services.telegram.webhook_secret' => 'hook-secret']);
        $this->fakeTelegram();
    }

    private bool $topicsWork = true;

    private function fakeTelegram(): void
    {
        Http::fake([
            'api.telegram.org/*createForumTopic' => fn () => $this->topicsWork
                ? Http::response(['ok' => true, 'result' => ['message_thread_id' => 77, 'name' => 'x']])
                : Http::response(['ok' => false, 'description' => 'Bad Request: the chat is not a forum'], 400),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 900]]),
        ]);
    }

    private function ticket(array $extra = []): SupportMessage
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 't'.$n, 'slug' => 'tp'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'TP'.$n, 'name' => 'Topic School '.$n, 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $school->id, 'type' => 'general', 'subject' => 'Printing', 'body' => 'It prints blank', 'telegram_message_id' => 3000 + $n, 'telegram_status' => 'sent']);
    }

    private function tap(SupportMessage $ticket, string $data, int $by = 11, string $name = 'Anna'): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['callback_query' => [
            'id' => 'cb1', 'from' => ['id' => $by, 'first_name' => $name], 'data' => 's:'.$ticket->id.':'.$data,
            'message' => ['message_id' => (int) $ticket->telegram_message_id, 'chat' => ['id' => -1004457287957]],
        ]])->assertOk();
    }

    private function say(string $text, array $extra = []): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 701, 'chat' => ['id' => -1004457287957], 'from' => ['id' => 11, 'first_name' => 'Anna'], 'text' => $text] + $extra])->assertOk();
    }

    private function sentTo(string $method, callable $check): void
    {
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/'.$method) && $check($r));
    }

    public function test_taking_a_request_opens_a_topic_with_the_client_message_and_links_it_on_the_card(): void
    {
        $ticket = $this->ticket();

        $this->tap($ticket, 'take');

        $this->assertSame(77, (int) $ticket->fresh()->telegram_thread_id);
        // The title is short so the subject stays readable in the topic list: short number, subject, requester.
        $this->sentTo('createForumTopic', fn ($r) => str_starts_with((string) $r['name'], '#'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT).' · Printing · '.$ticket->user->name) && ! str_contains((string) $r['name'], 'SUP-'));
        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], 'It prints blank') && str_contains((string) $r['text'], $ticket->ticket_no));
        $this->sentTo('editMessageText', fn ($r) => str_contains((string) $r['text'], 'https://t.me/c/4457287957/77'));
    }

    public function test_assigning_again_does_not_open_a_second_topic(): void
    {
        $ticket = $this->ticket();
        $this->tap($ticket, 'take');
        $this->tap($ticket, 'noop', 22, 'Ben');

        $this->tap($ticket, 'to:22');

        $this->assertSame(77, (int) $ticket->fresh()->telegram_thread_id);
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'createForumTopic')));
    }

    public function test_a_group_without_topics_still_works_and_the_request_is_still_assigned(): void
    {
        $this->topicsWork = false;
        $ticket = $this->ticket();

        $this->tap($ticket, 'take');

        $this->assertSame('Anna', $ticket->fresh()->assigned_name);
        $this->assertNull($ticket->fresh()->telegram_thread_id);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], 'Assigned to Anna'));
    }

    public function test_a_staff_message_written_in_the_topic_is_the_answer_to_that_request(): void
    {
        $ticket = $this->ticket(['telegram_thread_id' => 77]);

        $this->say('Please send a photo', ['message_thread_id' => 77, 'reply_to_message' => ['message_id' => 77]]);

        $reply = $ticket->replies()->first();
        $this->assertSame('staff', $reply->sender);
        $this->assertSame('Please send a photo', $reply->body);
        $this->assertSame('for_process', $ticket->fresh()->status);
        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], 'answered'));
    }

    public function test_a_command_in_the_topic_needs_no_number(): void
    {
        $ticket = $this->ticket(['telegram_thread_id' => 77]);

        $this->say('/hold', ['message_thread_id' => 77]);

        $this->assertSame('on_hold', $ticket->fresh()->status);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], $ticket->ticket_no.' · On hold') && (int) ($r['reply_to_message_id'] ?? 0) === (int) $ticket->telegram_message_id);
    }

    public function test_the_clients_web_reply_goes_into_the_topic(): void
    {
        $ticket = $this->ticket(['telegram_thread_id' => 77]);

        $this->actingAs($ticket->user)->post(route('support.reply', $ticket), ['body' => 'Here is the photo'])->assertRedirect();

        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], 'Here is the photo') && ! isset($r['reply_to_message_id']));
    }

    public function test_resolving_closes_the_topic_and_a_late_tap_does_not_close_it_again(): void
    {
        $ticket = $this->ticket(['telegram_thread_id' => 77]);

        $this->tap($ticket, 'resolve');
        $this->tap($ticket, 'hold');

        $this->sentTo('closeForumTopic', fn ($r) => (int) $r['message_thread_id'] === 77);
        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], 'Resolved'));
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'closeForumTopic')));
    }

    public function test_the_purge_deletes_the_topic_of_an_old_resolved_request(): void
    {
        $ticket = $this->ticket(['telegram_thread_id' => 77, 'status' => 'resolved', 'resolved_at' => now()->subDays(6)]);

        $this->artisan('support:purge')->assertSuccessful();

        $this->assertNull(SupportMessage::find($ticket->id));
        $this->sentTo('deleteForumTopic', fn ($r) => (int) $r['message_thread_id'] === 77);
    }

    public function test_the_topic_starts_with_a_header_showing_status_and_assignee_that_follows_every_change(): void
    {
        $ticket = $this->ticket();

        $this->tap($ticket, 'take');

        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], 'Status: Pending') && str_contains((string) $r['text'], 'Assigned: Anna')
            && str_contains(json_encode($r['reply_markup'] ?? []), 's:'.$ticket->id.':hold'));
        $this->assertSame(900, (int) $ticket->fresh()->telegram_thread_card_id);

        $this->tap($ticket, 'hold');

        $this->sentTo('editMessageText', fn ($r) => (int) $r['message_id'] === 900 && str_contains((string) $r['text'], 'Status: On hold'));
        $this->sentTo('sendMessage', fn ($r) => (int) ($r['message_thread_id'] ?? 0) === 77 && str_contains((string) $r['text'], $ticket->ticket_no.' · On hold'));
    }
}
