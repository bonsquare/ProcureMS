<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\PpmpPlan;
use App\Models\ProcurementRequest;
use App\Services\MasterTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The SOB replaced the PPMP. Old PPMP rows stay in the database and keep working where they were already used. */
class PpmpRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ppmp_routes_are_gone(): void
    {
        foreach (['planning.ppmp.store', 'planning.ppmp.update', 'planning.ppmp.destroy', 'planning.ppmp.approve'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->post('/planning/ppmp', ['aip_id' => $fx['aip']->id])->assertStatus(404);
    }

    public function test_the_planning_page_has_no_ppmp_card_or_form_and_reads_sip_aip_sob_app(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $page = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk();

        $page->assertSee('SIP · AIP · SOB · APP')->assertSee('School Operating Budget');
        $this->assertStringNotContainsString('PPMP', $page->getContent());
        $this->assertStringNotContainsString('Project Procurement Management Plan', $page->getContent());
        $page->assertDontSee('data-panel="ppmp"', false)->assertDontSee('dlg-ppmp', false)->assertDontSee('Save PPMP Draft');
    }

    public function test_the_aip_pages_no_longer_point_to_a_ppmp(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->get(route('aip.show', $fx['aip']))->assertOk()->assertDontSee('PPMP');
        $this->followingRedirects()->get(route('aip'))->assertOk()->assertDontSee('PPMP');
    }

    public function test_legacy_ppmp_rows_and_the_apps_made_from_them_still_work(): void
    {
        $fx = SobFixture::make();
        $ppmp = PpmpPlan::create(['school_id' => $fx['school']->id, 'aip_id' => $fx['aip']->id, 'master_transaction_id' => app(MasterTransactionService::class)->forAip($fx['aip'], $fx['user'])->id, 'fiscal_year' => 2026, 'project_title' => 'Old plan', 'status' => 'approved', 'created_by' => $fx['user']->id]);
        $ppmpItem = $ppmp->items()->create(['organization_id' => $fx['organization']->id, 'procurement_item' => 'Legacy item', 'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500]);
        $app = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => 'approved', 'created_by' => $fx['user']->id]);
        $legacy = AppItem::create(['organization_id' => $fx['organization']->id, 'app_plan_id' => $app->id, 'ppmp_item_id' => $ppmpItem->id, 'procurement_item' => 'Legacy item', 'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 250, 'estimated_total_cost' => 2500]);
        $this->actingAs($fx['user']);

        // A Purchase Request still draws from the legacy APP item, and the event lands on the old plan's transaction.
        $this->post(route('procurement.store'), [
            'school_id' => $fx['school']->id, 'purpose' => 'Office supplies', 'entity_name' => 'DepEd', 'department_name' => $fx['school']->name, 'request_date' => '2026-03-01', 'source_of_fund' => 'MOOE',
            'transaction_description' => null, 'section' => null, 'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null, 'extra_blank_rows' => 0,
            'items' => [['app_item_id' => $legacy->id, 'name' => 'Legacy item', 'quantity' => 4, 'unit' => 'ream', 'unit_price' => 250]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ProcurementRequest::count());
        $this->assertTrue($ppmp->fresh()->transaction->events()->where('action', 'pr_linked')->exists());

        $page = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->assertSee('Legacy item')->assertSee('4 of 10 ream');
        $this->assertStringNotContainsString('PPMP', $page->getContent());

        // The AIP of an old PPMP still cannot be deleted.
        Aip::withoutGlobalScopes()->whereKey($fx['aip']->id)->update(['status' => 'draft']);
        $this->delete(route('aip.destroy', $fx['aip']))->assertSessionHasErrors('aip');
        $this->assertNotNull(Aip::withoutGlobalScopes()->find($fx['aip']->id));
    }
}
