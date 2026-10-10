<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolTakeoverRequest;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Station Transfer module is now called Official Station Management and also receives the requests of people taking over a vacant school. */
class OfficialStationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user']);
    }

    private function newRequest(string $username = 'nina.lopez'): void
    {
        app(SchoolTakeoverService::class)->register([
            'name' => 'Nina R. Lopez', 'username' => $username, 'email' => $username.'@example.com', 'phone' => '0917', 'password' => 'secret-pass-1', 'position' => 'Administrative Officer',
        ], 'Waiting School');
    }

    public function test_the_masters_page_is_called_official_station_management_and_lists_the_requests_first(): void
    {
        $this->newRequest();

        $html = $this->actingAs($this->master())->get(route('transfer-requests'))->assertOk()
            ->assertSee('Official Station Management')->assertSee('Official Station Requests')->assertSee('Nina R. Lopez')->assertSee('Waiting School')
            ->assertDontSee('>Transfer requests</h1>', false)->getContent();

        $this->assertLessThan(strpos($html, 'Pending <span'), strpos($html, 'Official Station Requests'));
    }

    public function test_school_management_no_longer_lists_the_requests(): void
    {
        $this->newRequest();

        $this->actingAs($this->master())->get(route('school-management'))->assertOk()->assertDontSee('Takeover requests')->assertDontSee('Official Station Requests');
    }

    public function test_the_school_settings_tab_is_called_official_station_management(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        $this->actingAs($admin)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))->assertOk()
            ->assertSee('Official Station Management')->assertDontSee('Station Transfer');
    }

    public function test_the_menu_link_is_renamed_and_counts_the_waiting_requests(): void
    {
        $this->newRequest('first.person');
        $this->newRequest('second.person');

        $this->actingAs($this->master())->get(route('procurement'))->assertOk()
            ->assertSee('Official Station Management')->assertDontSee('Transfer requests')
            ->assertSee('rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">2</span>', false);
    }

    public function test_the_register_page_calls_the_option_an_official_station_request(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Official Station Request')->assertDontSee('Take Over Request');
    }

    public function test_the_sub_master_checklist_uses_the_new_name(): void
    {
        $this->actingAs($this->master())->get(route('user-management', ['tab' => 'master-user']))->assertOk()
            ->assertSee('Official Station Management')->assertDontSee('Transfer requests');
    }

    private function vacantSchool(string $name): School
    {
        $organization = Organization::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => strtoupper(substr($name, 0, 6)), 'name' => $name, 'status' => 'active']);
    }

    public function test_an_approved_request_moves_into_the_history_with_its_official_station(): void
    {
        $school = $this->vacantSchool('Lubas Elementary School');
        $this->newRequest();
        $master = User::factory()->create(['role' => 'master_user', 'name' => 'Real Master']);
        $request = SchoolTakeoverRequest::firstOrFail();

        app(SchoolTakeoverService::class)->approve($request, $master, 'Welcome aboard', $school->id);

        $html = $this->actingAs($master)->get(route('transfer-requests'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Official Station Requests <span', $html);
        $history = substr($html, strpos($html, '>History<'));
        foreach (['Nina R. Lopez', 'Official Station Request', 'Lubas Elementary School', 'Approved', 'by Real Master', 'Welcome aboard'] as $text) {
            $this->assertStringContainsString($text, $history, $text);
        }
    }

    public function test_a_declined_request_shows_in_the_history_without_a_station(): void
    {
        $this->newRequest();
        $master = User::factory()->create(['role' => 'master_user', 'name' => 'Real Master']);
        app(SchoolTakeoverService::class)->decline(SchoolTakeoverRequest::firstOrFail(), $master, 'Not authorised');

        $html = $this->actingAs($master)->get(route('transfer-requests'))->assertOk()->getContent();
        $history = substr($html, strpos($html, '>History<'));
        foreach (['Nina R. Lopez', 'Official Station Request', 'Declined', 'by Real Master', 'Not authorised'] as $text) {
            $this->assertStringContainsString($text, $history, $text);
        }
    }

    public function test_the_history_lists_requests_and_transfers_together_newest_first(): void
    {
        $school = $this->vacantSchool('Newest School');
        $master = User::factory()->create(['role' => 'master_user']);
        $mover = User::factory()->create(['role' => 'school_admin', 'name' => 'Older Mover']);
        StationTransferRequest::create([
            'user_id' => $mover->id, 'from_school_id' => $school->id, 'to_school_id' => $school->id, 'reason' => 'Reassigned', 'status' => 'approved',
            'requested_at' => now()->subDays(5), 'decided_at' => now()->subDays(4), 'decided_by' => $master->id,
        ]);
        $this->newRequest();
        app(SchoolTakeoverService::class)->approve(SchoolTakeoverRequest::firstOrFail(), $master, null, $school->id);

        $html = $this->actingAs($master)->get(route('transfer-requests'))->assertOk()->getContent();
        $history = substr($html, strpos($html, '>History<'));

        $this->assertNotFalse(strpos($history, 'Nina R. Lopez'));
        $this->assertNotFalse(strpos($history, 'Older Mover'));
        $this->assertLessThan(strpos($history, 'Older Mover'), strpos($history, 'Nina R. Lopez'));
    }
}
