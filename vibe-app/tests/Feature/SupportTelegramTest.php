<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_a_message_is_saved_and_forwarded_to_telegram(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 901]])]);
        $user = $this->user('tg1');

        $this->actingAs($user)->post(route('support.send'), ['body' => 'My DV is not showing'])->assertRedirect(route('support'));

        $message = SupportMessage::first();
        $this->assertSame(901, (int) $message->telegram_message_id);
        $this->assertSame('sent', $message->telegram_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'botTEST-TOKEN/sendMessage') && $request['chat_id'] === '555' && str_contains($request['text'], 'My DV is not showing') && str_contains($request['text'], 'School tg1'));
    }

    public function test_without_a_bot_the_message_is_only_saved(): void
    {
        config(['services.telegram.token' => null, 'services.telegram.chat_id' => null]);
        Http::fake();
        $user = $this->user('tg2');

        $this->actingAs($user)->post(route('support.send'), ['body' => 'Hello there'])->assertRedirect(route('support'));

        $this->assertSame('not_configured', SupportMessage::first()->telegram_status);
        Http::assertNothingSent();
    }

    public function test_a_reply_written_in_telegram_comes_back_to_the_user_and_nobody_else_can_answer(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 902]])]);
        $user = $this->user('tg3');
        $other = $this->user('tg4');
        $this->actingAs($user)->post(route('support.send'), ['body' => 'Need help'])->assertRedirect();
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
}
