<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\Organization;
use App\Models\PpmpItem;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $key = uniqid('pl');
        $organization = Organization::create(['name' => $key, 'slug' => $key, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($key), 'name' => 'Planning School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        return [$organization, $school, $master, $viewer];
    }

    private function sip(Organization $organization, School $school): SipProject
    {
        $sip = SipProject::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year' => 2026, 'planning_period' => '2026-2028', 'pillar' => 'Access', 'kra' => 'KRA 3', 'project' => 'Old program name']);
        $sip->activities()->create(['organization_id' => $organization->id, 'activity' => 'Old activity', 'physical_year1' => 1, 'financial_year1' => 500, 'financial_year2' => 500, 'financial_year3' => 500, 'source_of_fund' => 'MOOE', 'responsible_person' => 'School Head']);

        return $sip->load('activities');
    }

    private function ppmp(Organization $organization, School $school, string $status = 'draft'): PpmpPlan
    {
        $plan = PpmpPlan::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'project_title' => 'Old PPMP', 'procurement_mode' => 'Small Value Procurement', 'procurement_schedule' => 'Q1', 'fund_source' => 'MOOE', 'status' => $status]);
        $plan->items()->create(['organization_id' => $organization->id, 'procurement_item' => 'Bond paper', 'specifications' => 'A4', 'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500]);

        return $plan->load('items');
    }

    private function app(Organization $organization, School $school, string $status = 'draft', int $year = 2026): array
    {
        $plan = AppPlan::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => $year, 'status' => $status]);
        $source = $this->ppmp($organization, $school, 'approved')->items->first();
        $item = AppItem::create(['organization_id' => $organization->id, 'app_plan_id' => $plan->id, 'ppmp_item_id' => $source->id, 'procurement_item' => 'Bond paper', 'specifications' => 'A4', 'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500, 'procurement_mode' => 'Small Value Procurement', 'procurement_schedule' => 'Q1', 'fund_source' => 'MOOE']);

        return [$plan, $item];
    }

    public function test_a_sip_program_can_be_edited(): void
    {
        [$organization, $school, $master] = $this->world();
        $sip = $this->sip($organization, $school);

        $this->actingAs($master)->put(route('planning.sip.update', $sip), [
            'school_year' => 2026, 'planning_period' => '2026-2028', 'pillar' => 'Equity', 'kra' => 'KRA 2: Teaching and Learning Delivery',
            'organizational_outcome' => 'Learners stay in school', 'strategy' => 'Learner Support Management', 'five_point_agenda' => 'Enhanced Governance', 'project' => 'New program name',
        ])->assertRedirect();

        $sip->refresh();
        $this->assertSame(['Equity', 'KRA 2: Teaching and Learning Delivery', 'New program name', 'Learner Support Management'], [$sip->pillar, $sip->kra, $sip->project, $sip->strategy]);
        $this->put(route('planning.sip.update', $sip), ['school_year' => 2026, 'pillar' => 'Nonsense', 'kra' => 'x', 'project' => 'y'])->assertSessionHasErrors('pillar');
    }

    public function test_a_sip_program_can_be_deleted_with_its_activities_unless_an_aip_uses_it(): void
    {
        [$organization, $school, $master] = $this->world();
        $kept = $this->sip($organization, $school);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => 'draft', 'sip_project_id' => $kept->id]);
        $gone = $this->sip($organization, $school);

        $this->actingAs($master)->delete(route('planning.sip.destroy', $kept))->assertSessionHasErrors('planning');
        $this->assertNotNull(SipProject::withoutGlobalScopes()->find($kept->id));

        $this->delete(route('planning.sip.destroy', $gone))->assertRedirect();
        $this->assertNull(SipProject::withoutGlobalScopes()->find($gone->id));
        $this->assertSame(0, SipActivity::withoutGlobalScopes()->where('sip_project_id', $gone->id)->count());
        $this->assertSame(1, SipActivity::withoutGlobalScopes()->where('sip_project_id', $kept->id)->count());
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'sip_program_deleted', 'auditable_id' => $gone->id]);
        $this->assertNotNull($aip->fresh());
    }

    public function test_a_sip_activity_can_be_edited_and_the_program_budget_follows(): void
    {
        [$organization, $school, $master] = $this->world();
        $sip = $this->sip($organization, $school);
        $activity = $sip->activities->first();

        $this->actingAs($master)->put(route('planning.sip.activities.update', $activity), [
            'activity' => 'New activity', 'physical_year1' => 2, 'physical_year2' => 2, 'physical_year3' => 2,
            'financial_year1' => 1000, 'financial_year2' => 2000, 'financial_year3' => 3000,
            'source_of_fund' => 'SEF', 'responsible_person' => 'Teachers', 'remarks' => 'Updated',
        ])->assertRedirect();

        $activity->refresh();
        $this->assertSame(['New activity', 'SEF', 'Teachers', 'Updated'], [$activity->activity, $activity->source_of_fund, $activity->responsible_person, $activity->remarks]);
        $this->assertSame([1000.0, 2000.0, 3000.0], [(float) $activity->financial_year1, (float) $activity->financial_year2, (float) $activity->financial_year3]);
        $this->assertSame(6000.0, (float) $sip->fresh()->estimated_budget);
        $this->put(route('planning.sip.activities.update', $activity), ['activity' => ''])->assertSessionHasErrors('activity');
    }

    public function test_a_draft_ppmp_can_be_edited_and_deleted_but_an_approved_one_is_locked(): void
    {
        [$organization, $school, $master] = $this->world();
        $draft = $this->ppmp($organization, $school);
        $approved = $this->ppmp($organization, $school, 'approved');
        $this->actingAs($master);

        $this->put(route('planning.ppmp.update', $draft), [
            'project_title' => 'New PPMP', 'procurement_mode' => 'Shopping', 'procurement_schedule' => 'Q2', 'fund_source' => 'SEF',
            'procurement_item' => 'Long bond paper', 'specifications' => 'Legal', 'quantity' => 20, 'unit' => 'ream', 'estimated_unit_cost' => 300,
        ])->assertRedirect();
        $draft->refresh();
        $item = $draft->items->first();
        $this->assertSame(['New PPMP', 'Shopping', 'Q2', 'SEF'], [$draft->project_title, $draft->procurement_mode, $draft->procurement_schedule, $draft->fund_source]);
        $this->assertSame(['Long bond paper', 20.0, 6000.0], [$item->procurement_item, (float) $item->quantity, (float) $item->estimated_total_cost]);

        $this->put(route('planning.ppmp.update', $approved), ['project_title' => 'Changed', 'procurement_item' => 'x', 'quantity' => 1, 'unit' => 'pc', 'estimated_unit_cost' => 1])->assertSessionHasErrors('planning');
        $this->delete(route('planning.ppmp.destroy', $approved))->assertSessionHasErrors('planning');
        $this->assertSame('Old PPMP', $approved->fresh()->project_title);

        $this->delete(route('planning.ppmp.destroy', $draft))->assertRedirect();
        $this->assertNull(PpmpPlan::withoutGlobalScopes()->find($draft->id));
        $this->assertSame(0, PpmpItem::withoutGlobalScopes()->where('ppmp_plan_id', $draft->id)->count());
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'ppmp_deleted', 'auditable_id' => $draft->id]);
    }

    public function test_a_draft_app_item_can_be_edited_and_removed_but_an_approved_app_is_locked(): void
    {
        [$organization, $school, $master] = $this->world();
        [$draftPlan, $draftItem] = $this->app($organization, $school);
        $this->actingAs($master);

        $this->put(route('planning.app.items.update', $draftItem), [
            'procurement_item' => 'Long bond paper', 'specifications' => 'Legal', 'quantity' => 4, 'unit' => 'box', 'estimated_unit_cost' => 500,
            'procurement_mode' => 'Shopping', 'procurement_schedule' => 'Q3', 'fund_source' => 'SEF',
        ])->assertRedirect();
        $draftItem->refresh();
        $this->assertSame(['Long bond paper', 'box', 'Shopping', 'Q3', 'SEF', 2000.0], [$draftItem->procurement_item, $draftItem->unit, $draftItem->procurement_mode, $draftItem->procurement_schedule, $draftItem->fund_source, (float) $draftItem->estimated_total_cost]);

        $this->delete(route('planning.app.items.destroy', $draftItem))->assertRedirect();
        $this->assertNull(AppItem::withoutGlobalScopes()->find($draftItem->id));
        $this->assertNotNull(AppPlan::withoutGlobalScopes()->find($draftPlan->id));

        [, $lockedItem] = $this->app($organization, $school, 'approved', 2027);
        $this->put(route('planning.app.items.update', $lockedItem), ['procurement_item' => 'x', 'quantity' => 1, 'unit' => 'pc', 'estimated_unit_cost' => 1])->assertSessionHasErrors('planning');
        $this->delete(route('planning.app.items.destroy', $lockedItem))->assertSessionHasErrors('planning');
        $this->assertNotNull(AppItem::withoutGlobalScopes()->find($lockedItem->id));
    }

    public function test_only_someone_who_manages_planning_can_change_records(): void
    {
        [$organization, $school, , $viewer] = $this->world();
        $sip = $this->sip($organization, $school);
        $plan = $this->ppmp($organization, $school);
        $this->actingAs($viewer);

        $this->put(route('planning.sip.update', $sip), ['school_year' => 2026, 'pillar' => 'Access', 'kra' => 'x', 'project' => 'y'])->assertForbidden();
        $this->delete(route('planning.sip.destroy', $sip))->assertForbidden();
        $this->put(route('planning.sip.activities.update', $sip->activities->first()), ['activity' => 'x'])->assertForbidden();
        $this->put(route('planning.ppmp.update', $plan), ['project_title' => 'x'])->assertForbidden();
        $this->delete(route('planning.ppmp.destroy', $plan))->assertForbidden();
        $this->assertSame('Old program name', $sip->fresh()->project);
    }

    public function test_the_planning_page_shows_edit_and_delete_buttons(): void
    {
        [$organization, $school, $master] = $this->world();
        $sip = $this->sip($organization, $school);
        $draft = $this->ppmp($organization, $school);
        $this->ppmp($organization, $school, 'approved');
        $this->app($organization, $school);

        $html = $this->actingAs($master)->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString('data-edit-sip', $html);
        $this->assertStringContainsString('data-edit-activity', $html);
        $this->assertStringContainsString(route('planning.sip.destroy', $sip), $html);
        $this->assertStringContainsString(route('planning.ppmp.destroy', $draft), $html);
        $this->assertSame(1, substr_count($html, 'data-edit-ppmp'), 'only the draft PPMP can be edited');
        $this->assertStringContainsString('data-edit-app-item', $html);
        $this->assertStringContainsString('Approved plans are locked', $html);
    }
}
