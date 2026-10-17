<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routes without a `permission:` middleware for roles that the permission table (config/permissions.php) does not list
 * (BUGS.md B-010, B-011, B-012). B-010 (AIP view needs `aip.view`) and B-012 (Reports, Chart of Accounts and the PR form need their permission;
 * diagnostics stays open) were decided by the owner and are enforced; B-011 was decided as no change and still describes current behavior.
 */
class RoutePermissionGapsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAip(string $role): array
    {
        auth()->logout(); // set up each tenant as a system actor, not as the previous tenant's user
        $key = uniqid('gap');
        $organization = Organization::create(['name' => $key, 'slug' => $key, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($key), 'name' => 'Gap School', 'status' => 'active']);
        $user = User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => 'draft']);
        $kra = AipKra::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'pillar' => 'Access', 'kra' => 'KRA 1', 'program' => 'Program One']);
        $activity = AipActivity::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Listing', 'physical_target' => 1, 'q1_amount' => 1000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0]);

        return [$user, $aip, $activity];
    }

    public function test_b010_a_role_without_aip_view_cannot_open_the_aip_its_print_or_its_previews(): void
    {
        [$user, $aip, $activity] = $this->setUpAip('office_user');
        $this->assertFalse($user->hasPermission('aip.view'));

        $this->actingAs($user)->get(route('aip.show', $aip))->assertForbidden();
        $this->actingAs($user)->get(route('aip.print', $aip))->assertForbidden();
        $this->actingAs($user)->post(route('aip.activities.budget.preview', [$aip, $activity]), ['q1_amount' => 99999])->assertForbidden();
        $this->actingAs($user)->post(route('aip.activities.fund.preview', [$aip, $activity]), ['source_of_fund' => 'MOOE'])->assertForbidden();

        $this->assertEquals(1000, $activity->fresh()->q1_amount);
        $this->assertSame('draft', $aip->fresh()->status);
    }

    public function test_b010_roles_with_aip_view_still_open_the_aip_and_its_print(): void
    {
        foreach (['viewer', 'school_head', 'budget_officer', 'school_admin'] as $role) {
            [$user, $aip] = $this->setUpAip($role);
            $this->assertTrue($user->hasPermission('aip.view'), $role);

            $this->actingAs($user)->get(route('aip.show', $aip))->assertOk();
            $this->actingAs($user)->get(route('aip.print', $aip))->assertOk();
        }
    }

    public function test_b010_the_aip_previews_work_for_a_budget_officer_and_save_nothing(): void
    {
        [$user, $aip, $activity] = $this->setUpAip('budget_officer');

        $this->actingAs($user)->post(route('aip.activities.budget.preview', [$aip, $activity]), ['q1_amount' => 99999])->assertOk();
        $this->actingAs($user)->post(route('aip.activities.fund.preview', [$aip, $activity]), ['source_of_fund' => 'MOOE'])->assertOk();

        $this->assertEquals(1000, $activity->fresh()->q1_amount);
        $this->assertSame('draft', $aip->fresh()->status);
    }

    public function test_b010_a_role_without_manage_rights_cannot_really_change_the_aip(): void
    {
        [$user, $aip, $activity] = $this->setUpAip('office_user');

        $this->actingAs($user)->put(route('aip.activities.budget', [$aip, $activity]), ['q1_amount' => 99999])->assertForbidden();
        $this->actingAs($user)->post(route('aip.approve', $aip))->assertForbidden();

        $this->assertEquals(1000, $activity->fresh()->q1_amount);
        $this->assertSame('draft', $aip->fresh()->status);
    }

    public function test_b011_the_school_head_has_aip_approve_in_the_table_but_cannot_approve_an_aip(): void
    {
        [$user, $aip] = $this->setUpAip('school_head');
        $this->assertTrue($user->hasPermission('aip.approve'));

        $this->actingAs($user)->post(route('aip.approve', $aip))->assertForbidden();

        $this->assertSame('draft', $aip->fresh()->status);
    }

    public function test_b012_reports_chart_of_accounts_and_the_pr_form_refuse_roles_without_the_permission(): void
    {
        [$user] = $this->setUpAip('office_user');
        $this->assertFalse($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('accounting.view'));
        $this->assertFalse($user->hasPermission('accounting.manage'));

        $this->actingAs($user)->get(route('reports'))->assertForbidden();
        $this->actingAs($user)->get(route('chart-of-accounts'))->assertForbidden();

        [$viewer] = $this->setUpAip('viewer');
        $this->assertFalse($viewer->hasPermission('procurement.create'));
        $this->actingAs($viewer)->get(route('procurement.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('procurement.store'), [])->assertForbidden();
    }

    public function test_b012_roles_with_the_permission_still_open_these_pages(): void
    {
        foreach (['viewer', 'budget_officer', 'school_admin'] as $role) {
            [$user] = $this->setUpAip($role);
            $this->actingAs($user)->get(route('reports'))->assertOk();
        }
        foreach (['auditor', 'cashier', 'accounting_officer', 'budget_officer', 'school_admin'] as $role) {
            [$user] = $this->setUpAip($role);
            $this->actingAs($user)->get(route('chart-of-accounts'))->assertOk();
        }
        foreach (['office_user', 'encoder', 'procurement_officer', 'administrative_officer', 'school_admin'] as $role) {
            [$user] = $this->setUpAip($role);
            $this->actingAs($user)->get(route('procurement.create'))->assertOk();
        }
    }

    public function test_b012_the_diagnostics_page_stays_open_to_every_signed_in_role(): void
    {
        [$user] = $this->setUpAip('office_user');

        $this->actingAs($user)->get(route('dashboard.diagnostics'))->assertOk();
    }
}
