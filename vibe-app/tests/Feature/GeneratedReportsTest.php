<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratedReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reports_page_generates_the_accounting_and_cash_reports_itself_instead_of_linking_to_other_pages(): void
    {
        $organization = Organization::create(['name' => 'gen', 'slug' => 'gen', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'GEN', 'name' => 'Generated School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-1', 'ors_number' => 'ORS-1', 'ors_kind' => 'other',
            'amount' => 1000, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase', 'dv_number' => '2026-03-0001', 'dv_date' => '2026-03-12',
            'dv_non_cash' => false, 'paid_at' => '2026-03-12', 'payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-1', 'dv_deductions' => ['withholding_tax' => 100],
        ]);

        $this->actingAs($master)->get(route('reports'))->assertOk()
            ->assertSee(route('reports.generate', 'cash'), false)->assertSee(route('reports.generate', 'accounting'), false)
            ->assertDontSee('planning#aip', false);

        $this->actingAs($master)->get(route('reports.generate', ['type' => 'cash', 'year' => 2026]))->assertOk()
            ->assertSeeInOrder(['Cash Disbursement Report', 'CHK-1', 'Store', '1,000.00', '100.00', '900.00', 'TOTAL']);
        $this->actingAs($master)->get(route('reports.generate', ['type' => 'accounting', 'year' => now()->year]))->assertOk()
            ->assertSeeInOrder(['ORS-1', '2026-03-0001', 'Store', 'Cash', 'Approved', '1,000.00']);
        $this->actingAs($master)->get(route('reports.generate', ['type' => 'cash', 'year' => 2026, 'format' => 'csv']))->assertOk()->assertDownload('report-cash-2026.csv');
        $this->actingAs($master)->get(route('reports.generate', 'nonsense'))->assertNotFound();
    }

    public function test_the_other_generated_reports_open(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        foreach (['aip', 'allotment-registry', 'chart-of-accounts'] as $type) {
            $this->actingAs($master)->get(route('reports.generate', $type))->assertOk()->assertSee('Export to Excel');
        }
    }
}
