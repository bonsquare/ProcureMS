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

/** The master user works a support request inside the system, and every action is reported to the Customer Services Telegram chat. */
class SupportStaffTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $slug): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => 'School '.$slug, 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'name' => 'Boss Orong']);
    }

    private function request(User $user, array $extra = []): SupportMessage
    {
        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $user->school_id, 'type' => 'general', 'subject' => 'Printing', 'body' => 'My print is blank', 'telegram_message_id' => 71, 'telegram_status' => 'sent']);
    }

    private function telegramOn(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 90]])]);
    }

    public function test_the_master_answers_from_the_system_and_it_is_posted_in_telegram_and_seen_by_the_requester(): void
    {
        $this->telegramOn();
        $owner = $this->user('s1');
        $request = $this->request($owner);
        $master = $this->master();

        $this->actingAs($master)->post(route('support.staff-reply', $request), ['body' => 'We are fixing it'])->assertRedirect(route('support.show', $request))->assertSessionHas('success');

        $reply = SupportReply::firstOrFail();
        $this->assertSame('staff', $reply->sender);
        $this->assertSame($master->id, (int) $reply->user_id);
        $this->assertSame('for_process', $request->fresh()->status);
        Http::assertSent(fn ($r) => str_contains((string) $r['text'], 'answered from the system by Boss Orong') && str_contains((string) $r['text'], 'We are fixing it') && (int) ($r['reply_to_message_id'] ?? 0) === 71);

        $messages = $this->actingAs($owner)->getJson(route('support.widget', ['id' => $request->id]))->json('current.messages');
        $this->assertSame(['My print is blank', 'We are fixing it'], collect($messages)->pluck('body')->all());
        $this->assertFalse($messages[1]['mine']);
    }

    public function test_only_the_master_can_answer_or_change_a_request_from_the_system(): void
    {
        Http::fake();
        $owner = $this->user('s2');
        $request = $this->request($owner);
        $other = $this->user('s3');

        $this->actingAs($owner)->post(route('support.staff-reply', $request), ['body' => 'Self service'])->assertForbidden();
        $this->post(route('support.status', $request), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($other)->post(route('support.status', $request), ['action' => 'resolve'])->assertForbidden();

        $this->assertSame(0, SupportReply::count());
        $this->assertSame('pending', $request->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_resolved_request_cannot_be_answered_or_changed(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('s4'), ['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($this->master())->post(route('support.staff-reply', $request), ['body' => 'Late answer'])->assertSessionHas('error');
        $this->post(route('support.status', $request), ['action' => 'process'])->assertSessionHas('error');

        $this->assertSame(0, SupportReply::count());
        $this->assertSame('resolved', $request->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_master_sets_the_status_from_the_system_and_the_chat_is_told(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('s5'), ['type' => 'subscription', 'subject' => 'Renewal']);

        $this->actingAs($this->master())->post(route('support.status', $request), ['action' => 'approve'])->assertRedirect(route('support.show', $request));

        $this->assertSame('approved', $request->fresh()->status);
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'Approved — by Boss Orong (system)') && (int) ($r['reply_to_message_id'] ?? 0) === 71);
        Http::assertSent(fn ($r) => str_contains((string) $r->url(), 'editMessageText') && str_contains((string) ($r['text'] ?? ''), 'Status: Approved'));
    }

    public function test_resolving_from_the_system_ends_the_request(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('s6'));

        $this->actingAs($this->master())->post(route('support.status', $request), ['action' => 'resolve']);

        $request->refresh();
        $this->assertSame('resolved', $request->status);
        $this->assertNotNull($request->resolved_at);
    }

    public function test_taking_a_request_from_the_system_assigns_it_to_the_master(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('s7'));

        $this->actingAs($this->master())->post(route('support.status', $request), ['action' => 'take']);

        $this->assertSame('Boss Orong', $request->fresh()->assigned_name);
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'Assigned to Boss Orong'));
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('s8'));

        $this->actingAs($this->master())->post(route('support.status', $request), ['action' => 'delete'])->assertSessionHasErrors('action');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_without_telegram_the_answer_is_saved_here_and_the_master_is_told(): void
    {
        Http::fake();
        config(['services.telegram.token' => '', 'services.telegram.chat_id' => '']);
        $request = $this->request($this->user('s9'));

        $this->actingAs($this->master())->post(route('support.staff-reply', $request), ['body' => 'Saved only'])->assertSessionHas('success', fn (string $text) => str_contains($text, 'Telegram is not connected'));

        $this->assertSame(1, SupportReply::count());
        Http::assertNothingSent();
    }

    public function test_the_page_gives_the_master_the_actions_and_shows_where_each_answer_came_from(): void
    {
        $owner = $this->user('s10');
        $request = $this->request($owner, ['type' => 'subscription']);
        $request->replies()->create(['body' => 'From the chat', 'sender' => 'staff']);
        $master = $this->master();
        $request->replies()->create(['body' => 'From the desk', 'sender' => 'staff', 'user_id' => $master->id]);

        $this->actingAs($master)->get(route('support.show', $request))->assertOk()
            ->assertSee(route('support.status', $request), false)->assertSee(route('support.staff-reply', $request), false)
            ->assertSee('Approve')->assertSee('sent from Telegram')->assertSee('sent from the system')->assertSee('Subscriptions page');

        $this->actingAs($owner)->get(route('support.show', $request))->assertOk()
            ->assertDontSee(route('support.status', $request), false)->assertDontSee('sent from Telegram');
    }

    public function test_the_master_reads_the_conversation_as_customer_services_with_the_requester_details(): void
    {
        $owner = $this->user('s11');
        $request = $this->request($owner, ['subject' => 'Printing']);
        $request->replies()->create(['body' => 'We are on it', 'sender' => 'staff']);

        $page = $this->actingAs($this->master())->get(route('support.show', $request))->assertOk();
        $page->assertSee('Support desk')->assertSee($owner->email)->assertSee('Not assigned yet')->assertSee('Quick reply')->assertSee('Insert a ready answer')
            ->assertSee('items-end">', false);
        // The answer is on the right (as the master's own) and the requester on the left, with the name as the label.
        $page->assertSeeInOrder(['My print is blank', 'We are on it', 'Customer Services · ']);

        $this->actingAs($owner)->get(route('support.show', $request))->assertOk()->assertDontSee('Quick reply')->assertDontSee('Support desk')->assertDontSee('Not assigned yet');
    }
}
