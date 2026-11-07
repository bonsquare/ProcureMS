<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The rules staff work by in Telegram: who has a request, what state it is in, and what is still waiting. */
class SupportDeskTest extends TestCase
{
    use RefreshDatabase;

    private function request(string $subject, array $extra = []): SupportMessage
    {
        static $n = 0;
        $n++;
        $organization = Organization::create(['name' => 'd'.$n, 'slug' => 'd'.$n, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'D'.$n, 'name' => 'School '.$n, 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        return SupportMessage::create($extra + ['user_id' => $user->id, 'school_id' => $school->id, 'type' => 'general', 'subject' => $subject, 'body' => 'Body '.$subject]);
    }

    public function test_the_first_person_to_assign_wins(): void
    {
        $desk = new SupportDesk;
        $request = $this->request('One');

        $this->assertTrue($desk->assign($request, 111, 'Anna Reyes'));
        $this->assertFalse($desk->assign($request->fresh(), 222, 'Ben Cruz'));

        $this->assertSame('Anna Reyes', $request->fresh()->assigned_name);
        $this->assertSame(111, (int) $request->fresh()->assigned_telegram_id);
        $this->assertTrue($desk->assign($request->fresh(), 111, 'Anna Reyes'), 'the holder can take it again');
    }

    public function test_a_resolved_request_cannot_be_moved_back_and_resolving_sets_the_time(): void
    {
        $desk = new SupportDesk;
        $request = $this->request('Two');

        $this->assertTrue($desk->setStatus($request, 'on_hold'));
        $this->assertTrue($desk->setStatus($request->fresh(), 'resolved'));
        $this->assertNotNull($request->fresh()->resolved_at);
        $this->assertFalse($desk->setStatus($request->fresh(), 'for_process'));
        $this->assertSame('resolved', $request->fresh()->status);
        $this->assertFalse($desk->setStatus($request->fresh(), 'nonsense'));
    }

    public function test_the_queue_lists_unassigned_first_and_leaves_out_resolved(): void
    {
        $desk = new SupportDesk;
        $taken = $this->request('Taken one', ['status' => 'for_process', 'assigned_name' => 'Anna', 'assigned_telegram_id' => 5]);
        $free = $this->request('Free one');
        $this->request('Done one', ['status' => 'resolved', 'resolved_at' => now()]);

        $text = $desk->queue();

        $this->assertStringContainsString($free->ticket_no.' · Free one', $text);
        $this->assertStringContainsString($taken->ticket_no.' · Taken one', $text);
        $this->assertStringContainsString('Anna', $text);
        $this->assertStringContainsString('unassigned', $text);
        $this->assertStringNotContainsString('Done one', $text);
        $this->assertGreaterThan(strpos($text, 'Free one'), strpos($text, 'Taken one') === false ? 0 : strpos($text, 'Taken one'), 'Pending (unassigned) comes before For process');
    }

    public function test_an_empty_queue_says_so(): void
    {
        $this->assertStringContainsString('Nothing is waiting', (new SupportDesk)->queue());
    }

    public function test_mine_lists_only_my_requests_and_not_the_resolved_ones(): void
    {
        $desk = new SupportDesk;
        $mine = $this->request('Mine', ['status' => 'for_process', 'assigned_name' => 'Anna', 'assigned_telegram_id' => 5]);
        $this->request('Theirs', ['status' => 'for_process', 'assigned_name' => 'Ben', 'assigned_telegram_id' => 6]);
        $this->request('Mine done', ['status' => 'resolved', 'resolved_at' => now(), 'assigned_name' => 'Anna', 'assigned_telegram_id' => 5]);

        $text = $desk->mine(5);

        $this->assertStringContainsString($mine->ticket_no.' · Mine', $text);
        $this->assertStringNotContainsString('Theirs', $text);
        $this->assertStringNotContainsString('Mine done', $text);
    }

    public function test_assigning_by_name_marks_it_taken_and_shows_in_that_persons_mine(): void
    {
        $desk = new SupportDesk;
        $request = $this->request('By name');

        $desk->assignToName($request, '@ben');

        $this->assertSame('@ben', $request->fresh()->assigned_name);
        $this->assertFalse($desk->assign($request->fresh(), 999, 'Zed'), 'a request given to someone is taken');
        $this->assertStringContainsString('By name', $desk->mine(0, 'ben'));
    }
}
