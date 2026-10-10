<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementMenuTest extends TestCase
{
    use RefreshDatabase;

    /** Every page that has the side menu. */
    private const PAGES = [
        'home', 'procurement', 'budget', 'liquidation', 'accounting', 'reports', 'subscriptions',
        'user-management', 'google-drive', 'drive-files.index', 'backup.index',
    ];

    private function href(): string
    {
        return 'href="'.route('school-management').'"';
    }

    public function test_the_master_sees_school_management_in_the_menu_of_every_page(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (self::PAGES as $page) {
            $this->actingAs($master)->get(route($page))->assertOk()->assertSee($this->href(), false);
        }
    }

    public function test_a_sub_master_with_the_schools_access_sees_it_everywhere(): void
    {
        $sub = User::factory()->subMaster()->create();

        foreach (self::PAGES as $page) {
            $this->actingAs($sub)->get(route($page))->assertOk()->assertSee($this->href(), false);
        }
    }

    public function test_a_sub_master_without_the_schools_access_sees_it_nowhere(): void
    {
        $sub = User::factory()->subMaster(array_values(array_diff(SubMasterAccess::defaults(), ['schools'])))->create();

        foreach (self::PAGES as $page) {
            $this->actingAs($sub)->get(route($page))->assertOk()->assertDontSee($this->href(), false);
        }
    }

    public function test_a_school_admin_never_sees_it(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $admin = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        foreach (['home', 'procurement', 'budget', 'liquidation', 'accounting', 'reports', 'google-drive', 'drive-files.index'] as $page) {
            $this->actingAs($admin)->get(route($page))->assertOk()->assertDontSee($this->href(), false);
        }
    }
}
