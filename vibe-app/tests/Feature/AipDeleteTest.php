<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipKra;
use App\Models\Organization;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AipDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function setup_(string $status = 'draft'): array
    {
        $key = uniqid('ad');
        $organization = Organization::create(['name' => $key, 'slug' => $key, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($key), 'name' => 'AIP Delete School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => $status]);

        return [$aip, $master, $school, $organization];
    }

    public function test_a_draft_aip_can_be_deleted_with_its_kras_and_the_deletion_is_recorded(): void
    {
        [$aip, $master, $school] = $this->setup_();
        AipKra::create(['organization_id' => $aip->organization_id, 'aip_id' => $aip->id, 'pillar' => 'Access', 'kra' => 'KRA 3']);

        $this->actingAs($master)->delete(route('aip.destroy', $aip))->assertRedirect(route('planning', ['school_id' => $school->id, 'year' => 2026]).'#aip');

        $this->assertNull(Aip::withoutGlobalScopes()->find($aip->id));
        $this->assertSame(0, AipKra::withoutGlobalScopes()->where('aip_id', $aip->id)->count());
        $this->assertDatabaseHas('audit_logs', ['school_id' => $school->id, 'action' => 'aip_deleted', 'auditable_id' => $aip->id]);
    }

    public function test_an_approved_or_revised_aip_cannot_be_deleted(): void
    {
        foreach (['approved', 'revised'] as $status) {
            [$aip, $master] = $this->setup_($status);

            $this->actingAs($master)->delete(route('aip.destroy', $aip))->assertSessionHasErrors('aip');
            $this->assertNotNull(Aip::withoutGlobalScopes()->find($aip->id), $status);
        }
    }

    public function test_an_aip_with_a_ppmp_cannot_be_deleted(): void
    {
        [$aip, $master, $school, $organization] = $this->setup_();
        PpmpPlan::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'aip_id' => $aip->id, 'fiscal_year' => 2026, 'project_title' => 'PPMP 2026', 'status' => 'draft']);

        $this->actingAs($master)->delete(route('aip.destroy', $aip))->assertSessionHasErrors('aip');
        $this->assertNotNull(Aip::withoutGlobalScopes()->find($aip->id));
    }

    public function test_only_someone_who_manages_the_budget_can_delete(): void
    {
        [$aip, , $school, $organization] = $this->setup_();
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $organization->id, 'school_id' => $school->id]);

        $this->actingAs($viewer)->delete(route('aip.destroy', $aip))->assertForbidden();
        $this->assertNotNull(Aip::withoutGlobalScopes()->find($aip->id));
    }

    public function test_the_aip_list_offers_delete_only_where_it_is_allowed(): void
    {
        [$draft, $master, $school, $organization] = $this->setup_();
        Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2027, 'entity' => 'school', 'status' => 'approved']);

        $html = $this->actingAs($master)->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString(route('aip.destroy', $draft), $html);
        $this->assertSame(1, substr_count($html, 'data-delete-aip'), 'only the draft can be deleted');
        $this->assertStringContainsString('Only a draft AIP', $html);
    }
}
