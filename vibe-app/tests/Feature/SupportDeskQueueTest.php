<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The master user's support desk: a queue with filters and search, taking and assigning requests, and what is told to Telegram. */
class SupportDeskQueueTest extends TestCase
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

    private function request(User $user, string $subject, array $extra = []): SupportMessage
    {
        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $user->school_id, 'type' => 'general', 'subject' => $subject, 'body' => 'Body of '.$subject, 'telegram_message_id' => 71, 'telegram_status' => 'sent']);
    }

    private function subjects($response): array
    {
        return $response->viewData('messages')->pluck('subject')->all();
    }

    private function telegramOn(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 90]])]);
    }

    public function test_the_queue_shows_open_requests_with_unassigned_first_and_the_longest_waiting_first(): void
    {
        $owner = $this->user('q1');
        $this->request($owner, 'Old held', ['assigned_name' => 'Anna', 'created_at' => now()->subDays(3)]);
        $this->request($owner, 'Old free', ['created_at' => now()->subDays(2)]);
        $this->request($owner, 'New free', ['created_at' => now()->subDay()]);
        $this->request($owner, 'Done', ['status' => 'resolved', 'resolved_at' => now()]);

        $response = $this->actingAs($this->master())->get(route('support'))->assertOk();

        $this->assertSame(['Old free', 'New free', 'Old held'], $this->subjects($response));
    }

    public function test_the_filters_and_the_counts(): void
    {
        $owner = $this->user('q2');
        $master = $this->master();
        $this->request($owner, 'Mine one', ['assigned_name' => 'Boss Orong', 'status' => 'for_process']);
        $this->request($owner, 'Free pending');
        $this->request($owner, 'Held pending', ['assigned_name' => 'Anna']);
        $this->request($owner, 'On hold one', ['status' => 'on_hold', 'assigned_name' => 'Anna']);
        $this->request($owner, 'Done', ['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($master);
        $this->assertSame(['Free pending'], $this->subjects($this->get(route('support', ['filter' => 'unassigned']))));
        $this->assertSame(['Mine one'], $this->subjects($this->get(route('support', ['filter' => 'mine']))));
        $this->assertEqualsCanonicalizing(['Free pending', 'Held pending'], $this->subjects($this->get(route('support', ['filter' => 'pending']))));
        $this->assertSame(['On hold one'], $this->subjects($this->get(route('support', ['filter' => 'on_hold']))));
        $this->assertSame(['Done'], $this->subjects($this->get(route('support', ['filter' => 'resolved']))));
        $this->assertCount(4, $this->subjects($this->get(route('support', ['filter' => 'nonsense']))));

        $counts = $this->get(route('support'))->viewData('counts');
        $this->assertSame(['open' => 4, 'unassigned' => 1, 'mine' => 1, 'pending' => 2, 'for_process' => 1, 'on_hold' => 1, 'approved' => 0, 'resolved' => 1], collect($counts)->only(['open', 'unassigned', 'mine', 'pending', 'for_process', 'on_hold', 'approved', 'resolved'])->all());
    }

    public function test_search_finds_by_subject_message_person_or_school(): void
    {
        $alice = $this->user('qa');
        $alice->update(['name' => 'Alice Zamora']);
        $bob = $this->user('qb');
        $this->request($alice, 'Printer broken');
        $this->request($bob, 'Password reset', ['body' => 'Cashier forgot the login']);
        $master = $this->master();

        $this->actingAs($master);
        $this->assertSame(['Printer broken'], $this->subjects($this->get(route('support', ['q' => 'printer']))));
        $this->assertSame(['Password reset'], $this->subjects($this->get(route('support', ['q' => 'cashier']))));
        $this->assertSame(['Printer broken'], $this->subjects($this->get(route('support', ['q' => 'Zamora']))));
        $this->assertSame(['Password reset'], $this->subjects($this->get(route('support', ['q' => 'School qb']))));
        $this->assertSame([], $this->subjects($this->get(route('support', ['q' => 'nothing like this']))));
    }

    public function test_a_school_user_keeps_their_own_list_and_gets_no_desk(): void
    {
        $owner = $this->user('q3');
        $this->request($owner, 'Mine');
        $this->request($this->user('q4'), 'Not mine');

        $response = $this->actingAs($owner)->get(route('support', ['filter' => 'unassigned', 'q' => 'Not']))->assertOk();

        $this->assertSame(['Mine'], $this->subjects($response));
        $this->assertNull($response->viewData('counts'));
        $response->assertDontSee('Queue filters')->assertDontSee('Take next waiting request');
    }

    public function test_the_master_page_shows_the_desk_overview_and_the_queue_chips(): void
    {
        $this->request($this->user('q5'), 'Waiting one');

        $this->actingAs($this->master())->get(route('support'))->assertOk()
            ->assertSee('Support desk')->assertSee('Queue filters')->assertSee('Take next waiting request (1)')->assertSee('Waiting the longest, with nobody')->assertSee('Waiting one');
    }

    public function test_the_queue_feed_answers_the_master_and_never_shows_a_user_other_requests(): void
    {
        $owner = $this->user('q6');
        $this->request($owner, 'Mine');
        $other = $this->request($this->user('q7'), 'Secret one');

        $master = $this->actingAs($this->master())->getJson(route('support.queue'))->assertOk();
        $this->assertStringContainsString('Secret one', $master->json('list'));
        $this->assertStringContainsString('Support desk', $master->json('overview'));

        $user = $this->actingAs($owner)->getJson(route('support.queue', ['current' => $other->id]))->assertOk();
        $this->assertStringContainsString('Mine', $user->json('list'));
        $this->assertStringNotContainsString('Secret one', $user->json('list'));
        $this->assertSame('', $user->json('overview'));
    }

    public function test_the_master_assigns_a_request_to_a_staff_member_and_the_chat_is_told(): void
    {
        $this->telegramOn();
        app(SupportDesk::class)->rememberStaff(77, 'Anna Cruz');
        $request = $this->request($this->user('q8'), 'Help');

        $this->actingAs($this->master())->post(route('support.assign', $request), ['staff' => '77'])->assertRedirect(route('support.show', $request))->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('Anna Cruz', $request->assigned_name);
        $this->assertSame(77, (int) $request->assigned_telegram_id);
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'Assigned to Anna Cruz') && str_contains((string) ($r['text'] ?? ''), 'by Boss Orong (system)'));
    }

    public function test_the_master_takes_a_request_and_sends_it_back_to_the_queue(): void
    {
        $this->telegramOn();
        $request = $this->request($this->user('q9'), 'Help');
        $this->actingAs($this->master());

        $this->post(route('support.assign', $request), ['staff' => 'me'])->assertSessionHas('success');
        $this->assertSame('Boss Orong', $request->fresh()->assigned_name);

        $this->post(route('support.assign', $request), ['staff' => 'none'])->assertSessionHas('success');
        $request->refresh();
        $this->assertNull($request->assigned_name);
        $this->assertNull($request->assigned_telegram_id);
        Http::assertSent(fn ($r) => str_contains((string) ($r['text'] ?? ''), 'Back in the queue'));
    }

    public function test_an_unknown_staff_member_or_a_resolved_request_is_refused(): void
    {
        $this->telegramOn();
        $owner = $this->user('q10');
        $open = $this->request($owner, 'Open one');
        $done = $this->request($owner, 'Done one', ['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAs($this->master());

        $this->post(route('support.assign', $open), ['staff' => '999'])->assertSessionHas('error');
        $this->post(route('support.assign', $open), ['staff' => 'abc'])->assertSessionHas('error');
        $this->post(route('support.assign', $done), ['staff' => 'me'])->assertSessionHas('error');

        $this->assertNull($open->fresh()->assigned_name);
        $this->assertNull($done->fresh()->assigned_name);
        Http::assertNothingSent();
    }

    public function test_only_the_master_can_assign_or_take_next(): void
    {
        Http::fake();
        $owner = $this->user('q11');
        $request = $this->request($owner, 'Help');

        $this->actingAs($owner)->post(route('support.assign', $request), ['staff' => 'me'])->assertForbidden();
        $this->post(route('support.take-next'))->assertForbidden();

        $this->assertNull($request->fresh()->assigned_name);
        Http::assertNothingSent();
    }

    public function test_take_next_takes_the_request_that_has_waited_longest_without_anyone(): void
    {
        $this->telegramOn();
        $owner = $this->user('q12');
        $this->request($owner, 'Held', ['assigned_name' => 'Anna', 'created_at' => now()->subDays(5)]);
        $oldest = $this->request($owner, 'Oldest free', ['created_at' => now()->subDays(2)]);
        $newer = $this->request($owner, 'Newer free', ['created_at' => now()->subDay()]);
        $this->request($owner, 'Done', ['status' => 'resolved', 'resolved_at' => now(), 'created_at' => now()->subDays(9)]);

        $this->actingAs($this->master())->post(route('support.take-next'))->assertRedirect(route('support.show', $oldest));

        $this->assertSame('Boss Orong', $oldest->fresh()->assigned_name);
        $this->assertNull($newer->fresh()->assigned_name);
    }

    public function test_take_next_with_nothing_waiting_says_so(): void
    {
        Http::fake();
        $this->request($this->user('q13'), 'Held', ['assigned_name' => 'Anna']);

        $this->actingAs($this->master())->post(route('support.take-next'))->assertRedirect(route('support'))->assertSessionHas('success', fn (string $text) => str_contains($text, 'Nothing is waiting'));

        Http::assertNothingSent();
    }

    public function test_the_master_bell_counts_the_requests_waiting_for_someone(): void
    {
        $owner = $this->user('q14');
        $this->request($owner, 'Free one');
        $this->request($owner, 'Free two');
        $this->request($owner, 'Held', ['assigned_name' => 'Anna']);
        $master = $this->master();

        $items = collect($this->actingAs($master)->getJson(route('notifications'))->json('items'))->filter(fn (array $item) => str_contains($item['title'], 'waiting for someone'))->values();
        $this->assertCount(1, $items);
        $this->assertSame('2 support requests waiting for someone', $items[0]['title']);
        $this->assertSame(route('support', ['filter' => 'unassigned']), $items[0]['url']);

        $none = collect($this->actingAs($owner)->getJson(route('notifications'))->json('items'))->filter(fn (array $item) => str_contains($item['title'], 'waiting for someone'));
        $this->assertCount(0, $none);
    }
}
