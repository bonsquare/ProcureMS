<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The Server Dashboard tab: Railway hosting and domain figures, for the Super Admin only. */
class ServerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    public function test_the_master_sees_hosting_and_domain_figures(): void
    {
        Cache::flush();
        config(['services.railway.token' => 'tok', 'services.railway.project_id' => 'proj', 'services.server_dashboard.url' => 'http://example.test', 'services.server_dashboard.domain_expires_at' => now()->addDays(30)->toDateString()]);
        Http::fake([
            'backboard.railway.com/*' => Http::response(['data' => [
                'project' => ['name' => 'procurems', 'services' => ['edges' => [['node' => ['name' => 'procurems']]]], 'environments' => ['edges' => [['node' => ['name' => 'production']]]]],
                'deployments' => ['edges' => [['node' => ['status' => 'SUCCESS', 'createdAt' => '2026-10-13T22:18:00Z']]]],
                'estimatedUsage' => [['measurement' => 'NETWORK_TX_GB', 'estimatedValue' => 10], ['measurement' => 'MEMORY_USAGE_GB', 'estimatedValue' => 1000]],
            ]]),
            '*' => Http::response('ok'),
        ]);

        $this->actingAs($this->master())->get(route('home', ['view' => 'server']))
            ->assertOk()
            ->assertSee('Server dashboard', false)
            ->assertSee('$0.73')
            ->assertSee('SUCCESS')
            ->assertSee('Online');

        $this->assertSame([], array_filter(array_merge(...array_values(array_map(fn ($part) => is_array($part) ? $part : [], Cache::get('server-dashboard')))), 'is_object'), 'Only plain values may be cached.');
    }

    public function test_without_a_token_the_dashboard_says_how_to_connect_railway(): void
    {
        Cache::flush();
        config(['services.railway.token' => null, 'services.server_dashboard.url' => 'http://example.test']);
        Http::fake(['*' => Http::response('ok')]);

        $this->actingAs($this->master())->get(route('home', ['view' => 'server']))
            ->assertOk()
            ->assertSee('RAILWAY_API_TOKEN');
    }

    public function test_other_roles_get_no_server_tab_or_data(): void
    {
        $subMaster = User::factory()->create(['role' => 'sub_master', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($subMaster)->get(route('home', ['view' => 'server']))
            ->assertOk()
            ->assertDontSee('Server Dashboard')
            ->assertDontSee('Server dashboard', false);
    }
}
