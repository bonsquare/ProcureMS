<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DvWithholdingTaxTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $taxType, float $rate, string $kind, float $amount = 11200): LiquidationReport
    {
        $organization = Organization::create(['name' => 'wt', 'slug' => 'wt', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'WT', 'name' => 'WT School', 'status' => 'active']);
        Supplier::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'business_name' => 'Acme', 'tax_type' => $taxType, 'tax_rate' => $rate, 'status' => 'active']);

        return LiquidationReport::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-WT', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Acme', 'ors_kind' => $kind]);
    }

    public function test_vat_supplier_is_amount_over_1_12_times_the_suppliers_rate(): void
    {
        $this->assertSame(500.0, $this->report('vat', 5, 'goods')->suggestedWithholdingTax());
    }

    public function test_non_vat_supplier_is_the_amount_times_the_suppliers_rate(): void
    {
        $this->assertSame(300.0, $this->report('non_vat', 3, 'goods', 10000)->suggestedWithholdingTax());
    }

    public function test_exempt_or_unknown_payees_get_nothing(): void
    {
        $this->report('vat_exempt', 5, 'goods');
        $this->assertSame(0.0, LiquidationReport::first()->suggestedWithholdingTax());
        $this->assertSame(0.0, LiquidationReport::first()->fill(['payee' => 'Nobody'])->suggestedWithholdingTax());
    }
}
