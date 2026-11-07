<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTelegramTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $slug): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => 'School '.$slug, 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function configure(): void
    {
        config(['services.telegram.token' => 'TEST-TOKEN', 'services.telegram.chat_id' => '555', 'services.telegram.webhook_secret' => 'hook-secret']);
    }

    public function test_a_message_is_saved_and_emailed_to_customer_services(): void
    {
        config(['mail.default' => 'array']);
        SystemSetting::put('support_email', 'help@example.com');
        $user = $this->user('tg1');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Help', 'body' => 'My DV is not showing'])->assertRedirect(route('support'));

        $this->assertSame('sent', SupportMessage::first()->telegram_status);
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('help@example.com', $sent->getTo()[0]->getAddress());
        $this->assertSame($user->email, $sent->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('My DV is not showing', $sent->getTextBody());
    }

    public function test_a_message_is_also_sent_to_the_telegram_chat_and_a_reply_there_finds_it(): void
    {
        config(['mail.default' => 'array']);
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        $user = $this->user('tg-send');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Help', 'body' => 'Telegram please'])->assertRedirect(route('support'))->assertSessionHas('success');

        $message = SupportMessage::first();
        $this->assertSame(77, (int) $message->telegram_message_id);
        $this->assertSame('sent', $message->telegram_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r->body(), 'Telegram please') && str_contains($r->body(), 'Reply directly to this message'));
    }

    public function test_without_a_support_email_the_message_is_only_saved(): void
    {
        config(['mail.default' => 'array']);
        $user = $this->user('tg2');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Help', 'body' => 'Hello there'])->assertRedirect(route('support'));

        $this->assertSame('not_configured', SupportMessage::first()->telegram_status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_a_reply_written_in_telegram_comes_back_to_the_user_and_nobody_else_can_answer(): void
    {
        $this->configure();
        $user = $this->user('tg3');
        $other = $this->user('tg4');
        SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'Need help', 'telegram_message_id' => 902, 'telegram_status' => 'sent']);
        $url = route('telegram.webhook', 'hook-secret');
        $reply = fn (string $chat, array $extra) => ['update_id' => 1, 'message' => ['message_id' => 950, 'chat' => ['id' => (int) $chat], 'text' => 'We fixed it'] + $extra];

        $this->postJson(route('telegram.webhook', 'wrong'), $reply('555', ['reply_to_message' => ['message_id' => 902]]))->assertNotFound();
        $this->postJson($url, $reply('999', ['reply_to_message' => ['message_id' => 902]]))->assertOk();
        $this->assertSame(0, SupportMessage::first()->replies()->count(), 'a message from another chat is ignored');

        $this->postJson($url, $reply('555', ['reply_to_message' => ['message_id' => 902]]), ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();
        $message = SupportMessage::first()->fresh();
        $this->assertSame('for_process', $message->status);
        $this->assertSame('We fixed it', $message->replies->first()->body);

        $this->actingAs($user)->get(route('support.show', $message))->assertOk()->assertSee('We fixed it')->assertSee('On process');
        $this->actingAs($other)->get(route('support.show', $message))->assertNotFound();
        $this->actingAs($other)->get(route('support'))->assertOk()->assertDontSee('We fixed it')->assertDontSee('Need help');
    }

    public function test_a_hash_number_reply_also_answers_a_message(): void
    {
        $this->configure();
        $user = $this->user('tg5');
        $message = SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'Question', 'telegram_message_id' => 77, 'telegram_status' => 'sent']);

        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 10, 'chat' => ['id' => 555], 'text' => '#'.$message->id.' Please try again']])->assertOk();

        $this->assertSame('Please try again', $message->replies()->first()->body);
    }

    public function test_the_chat_is_told_which_message_was_answered(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
        $user = $this->user('tg6');
        $message = SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'Question', 'telegram_message_id' => 61, 'telegram_status' => 'sent']);

        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 12, 'chat' => ['id' => 555], 'text' => 'Done', 'reply_to_message' => ['message_id' => 61]]])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r->body(), $message->ticket_no.' answered') && str_contains($r->body(), '"reply_to_message_id":61'));
    }

    private function hook(array $message): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => $message + ['message_id' => 500, 'chat' => ['id' => 555]]])->assertOk();
    }

    public function test_the_visitors_email_is_not_put_in_the_telegram_message(): void
    {
        config(['mail.default' => 'array']);
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);
        $user = $this->user('tg7');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Help', 'body' => 'Private question'])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->body(), 'Private question') && ! str_contains($r->body(), $user->email));
    }

    public function test_a_staff_message_that_matches_no_ticket_is_not_delivered_and_staff_are_asked_to_reply_to_the_ticket(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
        $user = $this->user('tg8');
        $message = SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'Q', 'telegram_message_id' => 31, 'telegram_status' => 'sent']);

        $this->hook(['text' => 'Sorry for the wait']);
        $this->hook(['text' => '#9999 Wrong number', 'reply_to_message' => ['message_id' => 1]]);

        $this->assertSame(0, $message->replies()->count());
        $this->assertSame('pending', $message->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'not delivered') && str_contains($r->body(), 'Reply to the ticket message'));
    }

    public function test_staff_can_close_a_ticket_and_the_page_shows_it(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
        $user = $this->user('tg9');
        $byReply = SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'One', 'telegram_message_id' => 41, 'telegram_status' => 'sent']);
        $byNumber = SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'body' => 'Two', 'telegram_message_id' => 42, 'telegram_status' => 'sent']);

        $this->hook(['text' => '/close', 'reply_to_message' => ['message_id' => 41]]);
        $this->hook(['text' => '/close '.$byNumber->id]);

        $this->assertSame('resolved', $byReply->fresh()->status);
        $this->assertSame('resolved', $byNumber->fresh()->status);
        $this->actingAs($user)->get(route('support'))->assertSee('Resolved');
    }

    public function test_a_failed_telegram_send_is_logged(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        Log::spy();

        $this->assertNull(app(TelegramService::class)->send('hello'));

        Log::shouldHaveReceived('warning')->once();
    }
}
