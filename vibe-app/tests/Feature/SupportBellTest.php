<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The bell tells a user about new Customer Services answers on their own support requests, until they open them. */
class SupportBellTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $slug): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => 'School '.$slug, 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function request(User $user, string $subject = 'Printing'): SupportMessage
    {
        return SupportMessage::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'type' => 'general', 'subject' => $subject, 'body' => 'My print is blank', 'telegram_message_id' => 71, 'telegram_status' => 'sent']);
    }

    private function bell(User $user): array
    {
        return collect($this->actingAs($user)->getJson(route('notifications'))->assertOk()->json('items'))->filter(fn (array $item) => str_contains($item['title'], 'reply'))->values()->all();
    }

    public function test_no_alert_without_an_answer_from_customer_services(): void
    {
        $user = $this->user('b1');
        $request = $this->request($user);
        $request->replies()->create(['body' => 'Any news?', 'sender' => 'user', 'user_id' => $user->id]);

        $this->assertSame([], $this->bell($user));
    }

    public function test_a_staff_answer_shows_in_the_bell_and_opening_the_request_clears_it(): void
    {
        $user = $this->user('b2');
        $request = $this->request($user, 'Blank print');
        $request->replies()->create(['body' => 'We fixed it', 'sender' => 'staff']);

        $items = $this->bell($user);
        $this->assertCount(1, $items);
        $this->assertSame('New reply from Customer Services', $items[0]['title']);
        $this->assertSame('Blank print', $items[0]['detail']);
        $this->assertSame(route('support.show', $request), $items[0]['url']);

        $this->get(route('support.show', $request))->assertOk();
        $this->assertSame([], $this->bell($user));

        $request->replies()->create(['body' => 'One more thing', 'sender' => 'staff']);
        $this->assertCount(1, $this->bell($user));
    }

    public function test_several_answered_requests_are_one_bell_item_with_the_count(): void
    {
        $user = $this->user('b3');
        foreach (['One', 'Two'] as $subject) {
            $this->request($user, $subject)->replies()->create(['body' => 'Answer', 'sender' => 'staff']);
        }

        $items = $this->bell($user);
        $this->assertSame('2 support requests have a new reply', $items[0]['title']);
        $this->assertSame(route('support'), $items[0]['url']);
    }

    public function test_the_chat_popup_reading_a_conversation_clears_the_alert_but_the_list_does_not(): void
    {
        $user = $this->user('b4');
        $request = $this->request($user);
        $request->replies()->create(['body' => 'Answer', 'sender' => 'staff']);

        $this->actingAs($user)->getJson(route('support.widget'))->assertOk();
        $this->assertCount(1, $this->bell($user));

        $this->getJson(route('support.widget', ['id' => $request->id]))->assertOk();
        $this->assertSame([], $this->bell($user));
    }

    public function test_the_alert_goes_only_to_the_owner_and_a_master_reading_the_request_does_not_clear_it(): void
    {
        $owner = $this->user('b5');
        $request = $this->request($owner);
        $request->replies()->create(['body' => 'Answer', 'sender' => 'staff']);
        $master = User::factory()->create(['role' => 'master_user']);

        $this->assertSame([], $this->bell($master));
        $this->get(route('support.show', $request))->assertOk();
        $this->assertCount(1, $this->bell($owner));
    }

    public function test_the_bell_item_has_an_icon_and_a_key_that_changes_with_each_newer_reply(): void
    {
        $user = $this->user('b6');
        $request = $this->request($user);
        $request->replies()->create(['body' => 'First', 'sender' => 'staff']);
        $first = $this->bell($user)[0];

        $request->replies()->create(['body' => 'Second', 'sender' => 'staff']);
        $second = $this->bell($user)[0];

        $this->assertSame('support_agent', $first['icon']);
        $this->assertNotSame($first['key'], $second['key']);
    }

    public function test_the_master_user_also_sees_the_alert_for_their_own_request(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);
        $this->request($master)->replies()->create(['body' => 'Answer', 'sender' => 'staff']);

        $this->assertCount(1, $this->bell($master));
    }
}
