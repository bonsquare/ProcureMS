<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubMasterGatesTest extends TestCase
{
    use RefreshDatabase;

    /** key => route name that only that management area opens */
    private const GATES = [
        'backup' => 'backup.index',
        'subscriptions' => 'subscriptions',
        'schools' => 'school-management',
        'transfers' => 'transfer-requests',
    ];

    private function schoolAdmin(): User
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    public function test_each_management_area_opens_only_when_checked(): void
    {
        foreach (self::GATES as $key => $routeName) {
            $others = array_values(array_diff(SubMasterAccess::defaults(), [$key]));
            $without = User::factory()->subMaster($others)->create();
            $with = User::factory()->subMaster([$key])->create();

            $this->actingAs($without)->get(route($routeName))->assertForbidden();
            $this->actingAs($with)->get(route($routeName))->assertOk();
        }
    }

    public function test_the_default_sub_master_and_the_master_open_every_area(): void
    {
        $default = User::factory()->subMaster()->create();
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (self::GATES as $routeName) {
            $this->actingAs($default)->get(route($routeName))->assertOk();
            $this->actingAs($master)->get(route($routeName))->assertOk();
        }
    }

    public function test_a_sub_master_with_no_checked_area_opens_none_of_them(): void
    {
        $none = User::factory()->subMaster([])->create();

        foreach (self::GATES as $routeName) {
            $this->actingAs($none)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_a_school_admin_stays_forbidden_everywhere(): void
    {
        $admin = $this->schoolAdmin();

        foreach (array_merge(self::GATES, ['users' => 'user-management']) as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertForbidden();
        }
        $this->actingAs($admin)->post(route('backup.run'), ['role' => 'master_user'])->assertForbidden();
    }

    public function test_the_users_tab_needs_the_users_access_but_the_own_account_tab_does_not(): void
    {
        $without = User::factory()->subMaster(array_values(array_diff(SubMasterAccess::defaults(), ['users'])))->create();
        $with = User::factory()->subMaster(['users'])->create();

        $this->actingAs($with)->get(route('user-management'))->assertOk()->assertSee('Total Users');
        $this->actingAs($without)->get(route('user-management'))->assertOk()->assertDontSee('Total Users')->assertSee('Change password');
        $this->actingAs($without)->get(route('user-management', ['tab' => 'master-user']))->assertOk()->assertSee('Change password');
    }

    public function test_menus_show_only_the_checked_areas(): void
    {
        $without = User::factory()->subMaster(array_values(array_diff(SubMasterAccess::defaults(), ['subscriptions', 'transfers', 'schools'])))->create();
        $with = User::factory()->subMaster()->create();

        $page = $this->actingAs($without)->get(route('procurement'))->assertOk();
        $page->assertDontSee('href="'.route('subscriptions').'"', false);
        $page->assertDontSee('href="'.route('school-management').'"', false);
        $page->assertDontSee('href="'.route('transfer-requests').'"', false);

        $this->actingAs($with)->get(route('procurement'))->assertOk()
            ->assertSee('href="'.route('subscriptions').'"', false)->assertSee('href="'.route('school-management').'"', false);
    }
}
