<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A support request is a chat: the user and Customer Services answer each other on the same request. */
class SupportConversationTest extends TestCase
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

    public function test_a_user_cannot_open_or_answer_another_users_request(): void
    {
        $owner = $this->user('c1');
        $other = $this->user('c2');
        $request = $this->request($owner);

        $this->actingAs($other)->get(route('support.show', $request))->assertNotFound();
        $this->post(route('support.reply', $request), ['body' => 'Sneaky'])->assertNotFound();
        $this->assertSame(0, SupportReply::count());
    }

    public function test_a_user_can_answer_and_it_goes_to_telegram_under_the_request(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 80]])]);
        $user = $this->user('c3');
        $request = $this->request($user);

        $this->actingAs($user)->post(route('support.reply', $request), ['body' => 'It is still blank'])->assertRedirect(route('support.show', $request));

        $reply = SupportReply::first();
        $this->assertSame('user', $reply->sender);
        $this->assertSame($user->id, (int) $reply->user_id);
        $this->assertSame('It is still blank', $reply->body);
        Http::assertSent(fn ($r) => str_contains((string) $r['text'], $request->ticket_no) && str_contains((string) $r['text'], 'It is still blank') && (int) $r['reply_to_message_id'] === 71);
    }

    public function test_a_resolved_request_cannot_be_answered(): void
    {
        Http::fake();
        $user = $this->user('c4');
        $request = $this->request($user, ['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($user)->post(route('support.reply', $request), ['body' => 'One more thing'])->assertRedirect(route('support.show', $request))->assertSessionHas('error');

        $this->assertSame(0, SupportReply::count());
        $this->assertSame('resolved', $request->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_conversation_shows_both_sides_in_time_order(): void
    {
        $user = $this->user('c5');
        $request = $this->request($user);
        $request->replies()->create(['body' => 'First from staff', 'sender' => 'staff']);
        $request->replies()->create(['body' => 'Then the user', 'sender' => 'user', 'user_id' => $user->id]);

        $html = $this->actingAs($user)->get(route('support.show', $request))->assertOk()->assertSee('My print is blank')->getContent();

        $this->assertLessThan(strpos($html, 'Then the user'), strpos($html, 'First from staff'));
    }

    public function test_the_user_sees_only_the_first_name_of_the_person_handling_the_request(): void
    {
        $user = $this->user('c7');
        $request = $this->request($user, ['assigned_name' => 'Anna Reyes', 'assigned_telegram_id' => 5, 'status' => 'for_process']);

        $this->actingAs($user)->get(route('support.show', $request))->assertOk()->assertSee('Handled by Anna')->assertDontSee('Reyes');
    }

    public function test_a_reply_needs_text(): void
    {
        $user = $this->user('c6');
        $request = $this->request($user);

        $this->actingAs($user)->post(route('support.reply', $request), ['body' => ''])->assertSessionHasErrors('body');
        $this->assertSame(0, SupportReply::count());
    }

    public function test_the_open_conversation_can_be_refreshed_and_the_signature_changes_with_a_new_message(): void
    {
        $user = $this->user('c8');
        $request = $this->request($user);

        $first = $this->actingAs($user)->getJson(route('support.poll', $request))->assertOk()->assertJsonPath('open', true)->assertSee('My print is blank');
        $before = $first->json('signature');

        $request->replies()->create(['body' => 'We are checking it', 'sender' => 'staff']);

        $after = $this->getJson(route('support.poll', $request))->assertOk()->assertSee('We are checking it');
        $this->assertNotSame($before, $after->json('signature'));
    }

    public function test_the_refresh_reports_a_resolved_request_and_is_private(): void
    {
        $owner = $this->user('c9');
        $other = $this->user('c10');
        $request = $this->request($owner, ['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($owner)->getJson(route('support.poll', $request))->assertOk()->assertJsonPath('open', false);
        $this->actingAs($other)->getJson(route('support.poll', $request))->assertNotFound();
    }

    public function test_the_page_carries_what_the_refresh_needs(): void
    {
        $user = $this->user('c11');
        $request = $this->request($user);

        $this->actingAs($user)->get(route('support.show', $request))->assertOk()
            ->assertSee('data-poll="'.route('support.poll', $request).'"', false)
            ->assertSee('id="support-thread"', false);
    }
}
