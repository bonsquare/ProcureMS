<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceRowBadgesTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $dvNumber): array
    {
        $organization = Organization::create(['name' => 'rb', 'slug' => 'rb', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'RB', 'name' => 'Row School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $master->id, 'request_number' => 'PR-2026-001', 'title' => 'Supplies', 'amount' => 3000, 'status' => 'approved', 'source_of_fund' => 'MOOE']);
        ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'created_by' => $master->id, 'document_type' => 'purchase_order', 'document_number' => '2026-001', 'document_date' => '2026-10-15', 'status' => 'prepared']);
        LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'procurement_request_id' => $pr->id, 'report_number' => 'LR-RB-1', 'ors_number' => 'ORS-RB-1',
            'amount' => 3000, 'status' => 'approved', 'payee' => 'Row Supplier', 'purpose' => 'Supplies', 'dv_number' => $dvNumber, 'dv_date' => now()->toDateString(),
        ]);

        return [$master];
    }

    public function test_the_row_shows_the_po_number_next_to_the_pr_number_in_its_own_color(): void
    {
        [$master] = $this->row('DV-2026-0001');

        $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()
            ->assertSee('PR PR-2026-001')->assertSee('PO 2026-001')->assertSee('bg-amber-100', false);
        $this->get(route('cash', ['tab' => 'unpaid']))->assertOk()->assertSee('PO 2026-001');
    }

    public function test_a_dv_number_is_not_shown_with_a_second_dv_in_front_of_it(): void
    {
        [$master] = $this->row('DV-2026-0001');
        $html = $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()->getContent();

        $this->assertStringContainsString('DV-2026-0001', $html);
        $this->assertStringNotContainsString('DV DV-2026-0001', $html);
        $this->assertStringNotContainsString('Edit DV DV-', $html);
    }

    public function test_a_number_without_the_letters_still_gets_them(): void
    {
        [$master] = $this->row('2026-0002');

        $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()->assertSee('DV 2026-0002');
    }

    public function test_the_ors_number_has_its_own_color_and_the_supplier_has_an_icon(): void
    {
        [$master] = $this->row('DV-2026-0001');

        $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()
            ->assertSee('bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-800">ORS-RB-1', false)
            ->assertSee('storefront</span>Row Supplier', false);
    }
}
