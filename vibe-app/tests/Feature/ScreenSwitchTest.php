<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function schoolUser(): User
    {
        $organization = Organization::create(['name' => 'sw', 'slug' => 'sw', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SW', 'name' => 'Switch School', 'status' => 'active']);

        return User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_head']);
    }

    public function test_the_screen_is_on_by_default(): void
    {
        $this->get(route('login'))->assertOk();
        $this->assertFalse(SystemSetting::screenIsOff());
    }

    public function test_the_master_turns_the_screen_off_and_on(): void
    {
        $master = $this->master();

        $this->actingAs($master)->put(route('screen.update'), ['screen' => 'off'])->assertRedirect(route('home', ['view' => 'screen']));
        $this->assertTrue(SystemSetting::screenIsOff());
        $this->get(route('home', ['view' => 'screen']))->assertOk()->assertSee('The screen is OFF')->assertSee('Turn the screen on');

        $this->put(route('screen.update'), ['screen' => 'on']);
        $this->assertFalse(SystemSetting::screenIsOff());
        $this->get(route('home', ['view' => 'screen']))->assertOk()->assertSee('Turn the screen off');
    }

    public function test_while_the_screen_is_off_visitors_and_users_see_the_message_but_the_master_works(): void
    {
        SystemSetting::put(SystemSetting::SCREEN_OFF, '1');

        $this->get('/')->assertStatus(503)->assertSee('System Under Maintenance', false);
        $this->actingAs($this->schoolUser())->get(route('home'))->assertStatus(503)->assertSee('System Under Maintenance', false);

        $this->actingAs($this->master())->get(route('home'))->assertOk();
    }

    public function test_the_sign_in_page_and_the_health_check_stay_reachable_for_the_master(): void
    {
        SystemSetting::put(SystemSetting::SCREEN_OFF, '1');

        $this->get(route('login'))->assertStatus(503);
        $this->get(route('login', ['master' => 1]))->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_only_the_master_may_use_the_switch(): void
    {
        $this->actingAs($this->schoolUser())->put(route('screen.update'), ['screen' => 'off'])->assertForbidden();
        $this->assertFalse(SystemSetting::screenIsOff());
    }

    public function test_the_master_switches_single_tabs_off_and_users_see_that_tab_under_maintenance_while_the_rest_works(): void
    {
        $master = $this->master();
        $this->actingAs($master)->put(route('screen.tabs'), ['sections' => ['budget', 'chart_of_accounts']])->assertSessionHasNoErrors();
        $this->assertSame(['budget', 'chart_of_accounts'], SystemSetting::sectionsOff());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'screen_tabs_changed')->count());
        $this->get(route('home', ['view' => 'screen']))->assertOk()->assertSee('2 tabs are under maintenance');
        $this->actingAs($master)->get('/budget')->assertOk()->assertDontSee('is temporarily unavailable');

        auth()->logout();
        $this->get('/budget')->assertStatus(503)->assertSee('This Site is Under Maintenance', false)->assertSee('Budget is temporarily unavailable')->assertSee('Back to Dashboard');
        $this->get('/accounting/chart-of-accounts')->assertStatus(503)->assertSee('Chart of Accounts is temporarily unavailable');
        $this->get('/accounting')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();

        $user = $this->schoolUser();
        $this->actingAs($user)->get('/budget')->assertStatus(503);
        $this->assertNotSame(503, $this->actingAs($user)->get('/cash')->getStatusCode(), 'a tab that is on keeps working');
    }

    public function test_only_the_master_switches_tabs_and_unknown_tabs_are_refused(): void
    {
        $this->actingAs($this->schoolUser())->put(route('screen.tabs'), ['sections' => ['budget']])->assertForbidden();
        $this->assertSame([], SystemSetting::sectionsOff());

        $this->actingAs($this->master())->put(route('screen.tabs'), ['sections' => ['nonsense']])->assertSessionHasErrors('sections.0');
        $this->put(route('screen.tabs'), [])->assertSessionHasNoErrors();
        $this->assertSame([], SystemSetting::sectionsOff());
    }

    public function test_the_whole_system_off_wins_over_the_tabs(): void
    {
        SystemSetting::put(SystemSetting::SCREEN_OFF, '1');
        SystemSetting::put(SystemSetting::SCREEN_OFF_SECTIONS, json_encode(['budget']));

        $this->get('/budget')->assertStatus(503)->assertSee('System Under Maintenance', false);
    }
}
