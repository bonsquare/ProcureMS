<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A school admin who clicked "User Management" or "Subscriptions" in a page menu got a 403, because those links
 * were listed for everyone on several pages. They now show only to the accounts that may open them.
 */
class MasterOnlyMenuItemsTest extends TestCase
{
    use RefreshDatabase;

    /** Pages a school admin can open that carry the side menu. */
    private const SCHOOL_PAGES = ['home', 'procurement', 'budget', 'liquidation', 'accounting', 'reports', 'google-drive', 'drive-files.index'];

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

    private function link(string $route): string
    {
        return 'href="'.route($route).'"';
    }

    public function test_a_school_admin_sees_no_link_to_a_page_that_would_refuse_them(): void
    {
        $admin = $this->schoolAdmin();

        foreach (self::SCHOOL_PAGES as $page) {
            $this->actingAs($admin)->get(route($page))->assertOk()
                ->assertDontSee($this->link('user-management'), false)->assertDontSee($this->link('subscriptions'), false);
        }
        $this->actingAs($admin)->get(route('user-management'))->assertForbidden();
    }

    public function test_the_master_still_sees_both_links_everywhere(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        foreach (['home', 'procurement', 'budget', 'liquidation', 'accounting', 'reports', 'google-drive', 'drive-files.index'] as $page) {
            $this->actingAs($master)->get(route($page))->assertOk()
                ->assertSee($this->link('user-management'), false)->assertSee($this->link('subscriptions'), false);
        }
    }

    public function test_a_sub_master_sees_subscriptions_only_with_that_access_and_always_its_own_account_page(): void
    {
        $without = User::factory()->subMaster(array_values(array_diff(SubMasterAccess::defaults(), ['subscriptions'])))->create();

        foreach (['reports', 'google-drive', 'drive-files.index', 'procurement'] as $page) {
            $this->actingAs($without)->get(route($page))->assertOk()
                ->assertSee($this->link('user-management'), false)->assertDontSee($this->link('subscriptions'), false);
        }
    }
}
