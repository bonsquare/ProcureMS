<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The floating Support chat popup: its conversation feed and the JSON answers of the send and reply actions. */
class SupportChatPopupTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $slug): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => 'School '.$slug, 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function request(User $user, array $extra = []): SupportMessage
    {
        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $user->school_id, 'type' => 'general', 'subject' => 'Printing', 'body' => 'My print is blank', 'telegram_message_id' => 71, 'telegram_status' => 'sent']);
    }

    public function test_the_feed_is_empty_for_a_user_without_a_request(): void
    {
        $this->actingAs($this->user('w1'))->getJson(route('support.widget'))
            ->assertOk()->assertJson(['requests' => [], 'latest_staff' => null, 'current' => null]);
    }

    public function test_the_feed_lists_only_the_users_own_open_requests(): void
    {
        $user = $this->user('w2');
        $open = $this->request($user, ['subject' => 'Blank print']);
        $this->request($user, ['subject' => 'Old one', 'status' => 'resolved']);
        $this->request($this->user('w3'), ['subject' => 'Someone else']);

        $response = $this->actingAs($user)->getJson(route('support.widget'))->assertOk();
        $this->assertSame(['Blank print'], collect($response->json('requests'))->pluck('subject')->all());
        $this->assertSame($open->id, $response->json('requests.0.id'));
        $this->assertNull($response->json('current'));
    }

    public function test_each_listed_request_carries_its_own_newest_staff_answer(): void
    {
        $user = $this->user('w2b');
        $answered = $this->request($user, ['subject' => 'Answered']);
        $quiet = $this->request($user, ['subject' => 'Quiet']);
        $reply = $answered->replies()->create(['body' => 'Done', 'sender' => 'staff']);
        $answered->replies()->create(['body' => 'Thanks', 'sender' => 'user', 'user_id' => $user->id]);

        $ids = collect($this->actingAs($user)->getJson(route('support.widget'))->json('requests'))->pluck('latest_staff_id', 'id');
        $this->assertSame($reply->id, $ids[$answered->id]);
        $this->assertSame(0, $ids[$quiet->id]);
    }

    public function test_the_feed_gives_one_conversation_with_short_details_and_the_newest_staff_answer(): void
    {
        $user = $this->user('w4a');
        $request = $this->request($user);
        $request->replies()->create(['body' => 'We are checking', 'sender' => 'staff']);
        $staff = $request->replies()->create(['body' => 'Fixed, try again', 'sender' => 'staff']);
        $request->replies()->create(['body' => 'Thanks', 'sender' => 'user', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson(route('support.widget', ['id' => $request->id]))->assertOk();
        $response->assertJson(['latest_staff' => ['id' => $staff->id, 'body' => 'Fixed, try again', 'request_id' => $request->id], 'current' => ['id' => $request->id, 'subject' => 'Printing', 'open' => true, 'ticket' => $request->ticket_no]]);
        $this->assertSame(['My print is blank', 'We are checking', 'Fixed, try again', 'Thanks'], collect($response->json('current.messages'))->pluck('body')->all());
        $this->assertSame([true, false, false, true], collect($response->json('current.messages'))->pluck('mine')->all());
    }

    public function test_another_users_request_cannot_be_opened_through_the_feed(): void
    {
        $other = $this->request($this->user('w5a'));

        $this->actingAs($this->user('w6'))->getJson(route('support.widget', ['id' => $other->id]))->assertOk()->assertJson(['current' => null]);
    }

    public function test_the_feed_requires_a_signed_in_user(): void
    {
        $this->getJson(route('support.widget'))->assertUnauthorized();
    }

    public function test_a_new_message_and_an_answer_work_as_json(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 80]])]);
        $user = $this->user('w4');

        $this->actingAs($user)->postJson(route('support.send'), ['type' => 'general', 'subject' => 'Hello', 'body' => 'I need help'])->assertOk()->assertJson(['ok' => true, 'id' => SupportMessage::firstOrFail()->id]);
        $request = SupportMessage::firstOrFail();
        $this->postJson(route('support.reply', $request), ['body' => 'Any news?'])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, $request->replies()->where('sender', 'user')->count());
    }

    public function test_an_answer_on_a_resolved_request_is_refused_as_json(): void
    {
        $user = $this->user('w5');
        $request = $this->request($user, ['status' => 'resolved']);

        $this->actingAs($user)->postJson(route('support.reply', $request), ['body' => 'Hello again'])->assertStatus(422);
        $this->assertSame(0, $request->replies()->count());
    }

    public function test_files_on_a_request_and_on_an_answer_are_listed_with_their_drive_links(): void
    {
        $user = $this->user('w7');
        $request = $this->request($user);
        $reply = $request->replies()->create(['body' => 'See photo', 'sender' => 'user', 'user_id' => $user->id]);
        SupportAttachment::create(['support_message_id' => $request->id, 'user_id' => $user->id, 'drive_file_id' => 'FILE1', 'name' => 'first.png', 'mime' => 'image/png', 'size' => 10]);
        SupportAttachment::create(['support_message_id' => $request->id, 'support_reply_id' => $reply->id, 'user_id' => $user->id, 'drive_file_id' => 'FILE2', 'name' => 'second.pdf', 'mime' => 'application/pdf', 'size' => 10]);

        $messages = $this->actingAs($user)->getJson(route('support.widget', ['id' => $request->id]))->json('current.messages');
        $this->assertSame([['name' => 'first.png', 'url' => 'https://drive.google.com/file/d/FILE1/view']], $messages[0]['files']);
        $this->assertSame([['name' => 'second.pdf', 'url' => 'https://drive.google.com/file/d/FILE2/view']], $messages[1]['files']);
    }

    public function test_a_file_sent_without_a_google_drive_connection_saves_the_message_and_warns_as_json(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 80]])]);
        $user = $this->user('w8');
        $png = UploadedFile::fake()->createWithContent('shot.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $response = $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Hello', 'body' => 'See the screenshot', 'files' => [$png]], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $this->assertStringContainsString('Google Drive', (string) $response->json('warning'));
        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
    }

    public function test_a_file_of_a_wrong_type_is_refused_as_json(): void
    {
        $user = $this->user('w9');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => 'Hello', 'body' => 'A program', 'files' => [UploadedFile::fake()->create('virus.exe', 10)]], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, SupportMessage::count());
    }
}
