<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubMasterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_master_has_every_access_and_sees_all_schools(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        $this->assertTrue($master->isMaster());
        $this->assertFalse($master->isSubMaster());
        $this->assertTrue($master->seesAllSchools());
        foreach (['schools', 'backup', 'delete', 'anything-at-all'] as $key) {
            $this->assertTrue($master->hasAccess($key), $key);
        }
        $this->assertTrue($master->hasPermission('budget.view'));
    }

    public function test_a_sub_master_has_exactly_the_checked_areas(): void
    {
        $sub = User::factory()->subMaster(['schools', 'backup'])->create();

        $this->assertTrue($sub->isSubMaster());
        $this->assertFalse($sub->isMaster());
        $this->assertTrue($sub->seesAllSchools());
        $this->assertTrue($sub->hasAccess('schools'));
        $this->assertTrue($sub->hasAccess('backup'));
        $this->assertFalse($sub->hasAccess('subscriptions'));
        $this->assertFalse($sub->hasAccess('delete'));
    }

    public function test_null_empty_or_unknown_access_means_no_access(): void
    {
        foreach ([null, [], ['bogus']] as $access) {
            $sub = User::factory()->create(['role' => 'sub_master', 'access' => $access]);

            $this->assertFalse($sub->hasAccess('schools'));
            $this->assertFalse($sub->hasAccess('bogus'));
            $this->assertFalse($sub->seesAllSchools());
            $this->assertFalse($sub->hasPermission('budget.view'));
            $this->assertTrue($sub->isAnyMaster());
        }
    }

    public function test_sanitize_drops_unknown_and_duplicate_keys(): void
    {
        $this->assertSame(['schools', 'delete'], SubMasterAccess::sanitize(['schools', 'bogus', 'schools', 'delete', 7, null]));
        $this->assertSame([], SubMasterAccess::sanitize('schools'));
        $this->assertSame([], SubMasterAccess::sanitize(null));
    }

    public function test_defaults_are_the_13_areas_without_the_switches(): void
    {
        $defaults = SubMasterAccess::defaults();

        $this->assertCount(13, $defaults);
        foreach (['delete', 'deactivate_user', 'close_budget', 'edit_own_name'] as $switch) {
            $this->assertNotContains($switch, $defaults);
        }
        $this->assertContains('backup', $defaults);
        $this->assertCount(17, SubMasterAccess::keys());
    }

    public function test_work_areas_grant_their_permission_families(): void
    {
        $sub = User::factory()->subMaster(['accounting'])->create();

        $this->assertTrue($sub->hasPermission('accounting.approve'));
        $this->assertTrue($sub->hasPermission('accounting.manage'));
        $this->assertFalse($sub->hasPermission('cash.pay'));
        $this->assertTrue($sub->hasPermission('dashboard.view'));
        $this->assertTrue($sub->hasPermission('reports.view'));
    }

    public function test_the_default_checklist_grants_every_work_permission(): void
    {
        $sub = User::factory()->subMaster()->create();

        foreach (['planning.manage', 'aip.view', 'budget.manage', 'procurement.create', 'procurement.edit', 'supplier.manage', 'accounting.approve', 'cash.pay', 'liquidation.approve', 'organization.settings', 'inspection.view'] as $permission) {
            $this->assertTrue($sub->hasPermission($permission), $permission);
        }
        $this->assertFalse($sub->hasAccess('delete'));
    }

    public function test_other_roles_have_no_master_access(): void
    {
        $admin = User::factory()->create(['role' => 'school_admin', 'access' => SubMasterAccess::keys()]);

        $this->assertFalse($admin->isMaster());
        $this->assertFalse($admin->isSubMaster());
        $this->assertFalse($admin->seesAllSchools());
        $this->assertFalse($admin->hasAccess('backup'));
        $this->assertNotContains('sub_master', array_keys(User::ROLES));
    }
}
