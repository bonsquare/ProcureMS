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
 * Pins down what the routes without a `permission:` middleware do today for roles that the permission table
 * (config/permissions.php) does not list (BUGS.md B-010, B-011, B-012). These tests describe current behavior;
 * if the owner decides a route must be refused, the matching assertion is the one to flip.
 */
class RoutePermissionGapsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAip(string $role): array
    {
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

    public function test_b010_a_role_without_aip_view_can_open_the_aip_and_its_print(): void
    {
        [$user, $aip] = $this->setUpAip('office_user');
        $this->assertFalse($user->hasPermission('aip.view'));

        $this->actingAs($user)->get(route('aip.show', $aip))->assertOk();
        $this->actingAs($user)->get(route('aip.print', $aip))->assertOk();
    }

    public function test_b010_the_aip_previews_work_without_manage_rights_and_save_nothing(): void
    {
        [$user, $aip, $activity] = $this->setUpAip('office_user');
        $this->assertFalse($user->canManageBudget());

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

    public function test_b012_get_pages_without_a_permission_check_open_for_roles_the_table_does_not_list(): void
    {
        [$user] = $this->setUpAip('office_user');
        $this->assertFalse($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('accounting.view'));

        $this->actingAs($user)->get(route('reports'))->assertOk();
        $this->actingAs($user)->get(route('dashboard.diagnostics'))->assertOk();
        $this->actingAs($user)->get(route('chart-of-accounts'))->assertOk();
    }

    public function test_b012_the_procurement_form_opens_for_a_viewer_but_saving_is_refused(): void
    {
        [$user] = $this->setUpAip('viewer');
        $this->assertFalse($user->hasPermission('procurement.create'));

        $this->actingAs($user)->get(route('procurement.create'))->assertOk();
        $this->actingAs($user)->post(route('procurement.store'), [])->assertForbidden();
    }
}
