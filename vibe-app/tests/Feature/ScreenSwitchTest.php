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

        $this->get('/')->assertStatus(503)->assertSee('Link is not available');
        $this->actingAs($this->schoolUser())->get(route('home'))->assertStatus(503)->assertSee('Link is not available');

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
}
