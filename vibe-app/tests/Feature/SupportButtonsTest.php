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

/** The buttons under a request in the main Telegram group: take it, assign to a staff member, set the status; every change is recorded in the group. */
class SupportButtonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555', 'services.telegram.webhook_secret' => 'hook-secret']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 900]])]);
    }

    private function ticket(array $extra = []): SupportMessage
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 'b'.$n, 'slug' => 'b'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'B'.$n, 'name' => 'School '.$n, 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $school->id, 'type' => 'general', 'subject' => 'Printing', 'body' => 'Body', 'telegram_message_id' => 2000 + $n, 'telegram_status' => 'sent']);
    }

    private function tap(SupportMessage $ticket, string $data, int $by = 11, string $name = 'Anna', string $chat = '555'): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['callback_query' => [
            'id' => 'cb1', 'from' => ['id' => $by, 'first_name' => $name], 'data' => 's:'.$ticket->id.':'.$data,
            'message' => ['message_id' => (int) $ticket->telegram_message_id, 'chat' => ['id' => $chat]],
        ]])->assertOk();
    }

    private function sentTo(string $method, callable $check): void
    {
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/'.$method) && $check($r));
    }

    public function test_the_new_request_card_carries_the_buttons(): void
    {
        $school = School::create(['organization_id' => Organization::create(['name' => 'bc', 'slug' => 'bc', 'status' => 'active'])->id, 'code' => 'BC', 'name' => 'Card School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Printing', 'body' => 'It prints blank'])->assertRedirect();

        $id = SupportMessage::first()->id;
        $this->sentTo('sendMessage', fn ($r) => str_contains(json_encode($r['reply_markup']), 's:'.$id.':take')
            && str_contains(json_encode($r['reply_markup']), 's:'.$id.':pick')
            && str_contains(json_encode($r['reply_markup']), 's:'.$id.':hold')
            && str_contains(json_encode($r['reply_markup']), 's:'.$id.':close'));
    }

    public function test_take_it_assigns_the_person_who_tapped_records_it_and_refreshes_the_card(): void
    {
        $ticket = $this->ticket();

        $this->tap($ticket, 'take');

        $this->assertSame('Anna', $ticket->fresh()->assigned_name);
        $this->assertSame(11, (int) $ticket->fresh()->assigned_telegram_id);
        $this->sentTo('answerCallbackQuery', fn ($r) => $r['callback_query_id'] === 'cb1');
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], 'Assigned to Anna') && str_contains((string) $r['text'], $ticket->ticket_no) && (int) $r['reply_to_message_id'] === (int) $ticket->telegram_message_id);
        $this->sentTo('editMessageText', fn ($r) => str_contains((string) $r['text'], 'Assigned: Anna'));
    }

    public function test_a_second_person_cannot_take_a_request_that_is_taken(): void
    {
        $ticket = $this->ticket();
        $this->tap($ticket, 'take');

        $this->tap($ticket, 'take', 22, 'Ben');

        $this->assertSame('Anna', $ticket->fresh()->assigned_name);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], 'Anna already has '.$ticket->ticket_no));
    }

    public function test_assign_to_lists_the_staff_the_group_has_seen_and_picking_one_assigns_them(): void
    {
        $ticket = $this->ticket();
        $this->tap($ticket, 'noop', 22, 'Ben');
        $this->tap($ticket, 'pick', 11, 'Anna');

        $this->sentTo('editMessageReplyMarkup', fn ($r) => str_contains(json_encode($r['reply_markup']), 's:'.$ticket->id.':to:22') && str_contains(json_encode($r['reply_markup']), 's:'.$ticket->id.':to:11'));

        $this->tap($ticket, 'to:22', 11, 'Anna');

        $this->assertSame('Ben', $ticket->fresh()->assigned_name);
        $this->assertSame(22, (int) $ticket->fresh()->assigned_telegram_id);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], 'Assigned to Ben') && str_contains((string) $r['text'], 'by Anna'));
    }

    public function test_each_status_button_sets_the_status_and_posts_the_record_in_the_group(): void
    {
        $ticket = $this->ticket();

        foreach (['process' => ['for_process', 'On process'], 'hold' => ['on_hold', 'On hold'], 'pending' => ['pending', 'Pending']] as $action => [$status, $label]) {
            $this->tap($ticket, $action);
            $this->assertSame($status, $ticket->fresh()->status);
            $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], $ticket->ticket_no.' · '.$label.' — by Anna'));
        }
    }

    public function test_resolve_and_close_end_the_request_and_remove_the_buttons(): void
    {
        $resolved = $this->ticket();
        $closed = $this->ticket();

        $this->tap($resolved, 'resolve');
        $this->tap($closed, 'close');

        $this->assertSame('resolved', $resolved->fresh()->status);
        $this->assertNotNull($resolved->fresh()->resolved_at);
        $this->assertSame('resolved', $closed->fresh()->status);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], $resolved->ticket_no.' · Resolved — by Anna'));
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], $closed->ticket_no.' · Closed — by Anna'));
        $this->sentTo('editMessageText', fn ($r) => $r['reply_markup'] === ['inline_keyboard' => []]);
    }

    public function test_a_finished_request_ignores_further_taps(): void
    {
        $ticket = $this->ticket(['status' => 'resolved', 'resolved_at' => now()]);

        $this->tap($ticket, 'hold');

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->sentTo('sendMessage', fn ($r) => str_contains((string) $r['text'], $ticket->ticket_no.' is resolved'));
    }

    public function test_taps_from_another_chat_are_ignored(): void
    {
        $ticket = $this->ticket();

        $this->tap($ticket, 'take', 11, 'Anna', '999');

        $this->assertNull($ticket->fresh()->assigned_name);
        Http::assertNothingSent();
    }

    public function test_the_first_staff_answer_moves_pending_to_on_process_and_refreshes_the_card(): void
    {
        $ticket = $this->ticket();

        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 700, 'chat' => ['id' => 555], 'from' => ['id' => 11, 'first_name' => 'Anna'], 'text' => 'Please resend', 'reply_to_message' => ['message_id' => (int) $ticket->telegram_message_id]]])->assertOk();

        $this->assertSame('for_process', $ticket->fresh()->status);
        $this->sentTo('editMessageText', fn ($r) => str_contains((string) $r['text'], 'Status: On process'));
    }

    public function test_pending_can_also_be_set_by_command(): void
    {
        $ticket = $this->ticket(['status' => 'on_hold']);

        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 701, 'chat' => ['id' => 555], 'from' => ['id' => 11, 'first_name' => 'Anna'], 'text' => '/pending', 'reply_to_message' => ['message_id' => (int) $ticket->telegram_message_id]]])->assertOk();

        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_a_reply_to_a_bot_line_under_the_card_still_answers_that_request(): void
    {
        $ticket = $this->ticket();

        // Telegram nests the message the bot line was itself a reply to: here, the card.
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 702, 'chat' => ['id' => 555], 'from' => ['id' => 11, 'first_name' => 'Anna'], 'text' => 'Okay na po ba?',
            'reply_to_message' => ['message_id' => 9001, 'reply_to_message' => ['message_id' => (int) $ticket->telegram_message_id]]]])->assertOk();

        $this->assertSame('Okay na po ba?', $ticket->replies()->first()?->body);
    }

    private function typeAssign(SupportMessage $ticket, array $from): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 703, 'chat' => ['id' => 555], 'from' => $from, 'text' => '/assign', 'reply_to_message' => ['message_id' => (int) $ticket->telegram_message_id]]])->assertOk();
    }

    public function test_typing_assign_on_a_taken_request_offers_the_staff_instead_of_reassigning(): void
    {
        $ticket = $this->ticket(['assigned_name' => 'Anna', 'assigned_telegram_id' => 11]);
        $this->tap($ticket, 'noop', 22, 'Ben');

        $this->typeAssign($ticket, ['id' => 22, 'first_name' => 'Ben']);

        $this->assertSame('Anna', $ticket->fresh()->assigned_name);
        $this->sentTo('sendMessage', fn ($r) => str_contains(json_encode($r['reply_markup'] ?? []), 's:'.$ticket->id.':to:22'));
    }

    public function test_assign_typed_by_the_group_itself_offers_the_staff_and_never_assigns_the_group(): void
    {
        $ticket = $this->ticket();
        $this->tap($ticket, 'noop', 22, 'Ben');

        $this->typeAssign($ticket, ['id' => 1087968824, 'is_bot' => true, 'first_name' => 'Group', 'username' => 'GroupAnonymousBot']);

        $this->assertNull($ticket->fresh()->assigned_name);
        $this->sentTo('sendMessage', fn ($r) => str_contains(json_encode($r['reply_markup'] ?? []), 's:'.$ticket->id.':to:22'));
    }

    public function test_picking_from_the_typed_list_assigns_and_removes_that_list(): void
    {
        $ticket = $this->ticket(['assigned_name' => 'Anna', 'assigned_telegram_id' => 11]);
        $this->tap($ticket, 'noop', 22, 'Ben');

        $this->postJson(route('telegram.webhook', 'hook-secret'), ['callback_query' => ['id' => 'cb2', 'from' => ['id' => 11, 'first_name' => 'Anna'], 'data' => 's:'.$ticket->id.':to:22',
            'message' => ['message_id' => 5555, 'chat' => ['id' => 555]]]])->assertOk();

        $this->assertSame('Ben', $ticket->fresh()->assigned_name);
        $this->sentTo('editMessageText', fn ($r) => (int) $r['message_id'] === 5555 && $r['reply_markup'] === ['inline_keyboard' => []] && str_contains((string) $r['text'], 'Assigned to Ben'));
    }
}
