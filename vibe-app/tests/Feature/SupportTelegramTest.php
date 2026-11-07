<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $this->actingAs($user)->post(route('support.send'), ['body' => 'My DV is not showing'])->assertRedirect(route('support'));

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

        $this->actingAs($user)->post(route('support.send'), ['body' => 'Telegram please'])->assertRedirect(route('support'))->assertSessionHas('success');

        $message = SupportMessage::first();
        $this->assertSame(77, (int) $message->telegram_message_id);
        $this->assertSame('sent', $message->telegram_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r->body(), 'Telegram please') && str_contains($r->body(), 'Reply to this message'));
    }

    public function test_without_a_support_email_the_message_is_only_saved(): void
    {
        config(['mail.default' => 'array']);
        $user = $this->user('tg2');

        $this->actingAs($user)->post(route('support.send'), ['body' => 'Hello there'])->assertRedirect(route('support'));

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
        $this->assertSame('answered', $message->status);
        $this->assertSame('We fixed it', $message->replies->first()->body);

        $this->actingAs($user)->get(route('support'))->assertOk()->assertSee('We fixed it')->assertSee('Answered');
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

        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r->body(), 'Support #'.$message->id.' answered') && str_contains($r->body(), '"reply_to_message_id":61'));
    }
}
