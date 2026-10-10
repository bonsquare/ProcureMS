<?php

namespace Tests\Feature;

use App\Models\SchoolTakeoverRequest;
use App\Models\StationTransferRequest;
use App\Models\User;
use App\Services\DemoResetService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reset_command_refuses_to_run_outside_demo_mode(): void
    {
        config(['app.demo' => false]);

        $this->artisan('demo:reset', ['--force' => true])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_the_showcase_has_accounts_and_open_requests_for_every_screen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoShowcaseSeeder::class);
        $this->seed(DemoShowcaseSeeder::class); // safe to run twice

        $sub = User::where('email', 'submaster@procurems.test')->firstOrFail();
        $this->assertTrue($sub->isSubMaster());
        $this->assertTrue($sub->hasAccess('users'));
        $this->assertFalse($sub->hasAccess('delete'));

        $this->assertSame(1, SchoolTakeoverRequest::where('status', 'pending')->whereNull('school_id')->count());
        $this->assertSame(['official_station', 'other', 'transfer'], StationTransferRequest::where('status', 'pending')->orderBy('kind')->pluck('kind')->all());
        $this->assertSame(1, User::where('status', 'pending')->count());
    }

    public function test_the_demo_login_page_lists_the_accounts_only_in_demo_mode(): void
    {
        config(['app.demo' => true]);
        $this->get(route('login'))->assertOk()->assertSee('admin@procurems.test')->assertSee('submaster@procurems.test');

        config(['app.demo' => false]);
        $this->get(route('login'))->assertOk()->assertDontSee('submaster@procurems.test');
    }

    public function test_the_reset_button_is_for_the_master_in_demo_mode_only(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);
        $sub = User::factory()->subMaster()->create();
        $reset = Mockery::mock(DemoResetService::class);
        $this->app->instance(DemoResetService::class, $reset);

        config(['app.demo' => false]);
        $reset->shouldNotReceive('reset');
        $this->actingAs($master)->post(route('demo.reset'))->assertNotFound();
        $this->actingAs($master)->get(route('user-management', ['tab' => 'master-user']))->assertOk()->assertDontSee('Reset demo data');

        config(['app.demo' => true]);
        $this->actingAs($sub)->post(route('demo.reset'))->assertForbidden();
        $this->actingAs($master)->get(route('user-management', ['tab' => 'master-user']))->assertOk()->assertSee('Reset demo data');

        $reset = Mockery::mock(DemoResetService::class);
        $reset->shouldReceive('reset')->once();
        $this->app->instance(DemoResetService::class, $reset);
        $this->actingAs($master)->post(route('demo.reset'))->assertRedirect(route('login'));
    }
}
