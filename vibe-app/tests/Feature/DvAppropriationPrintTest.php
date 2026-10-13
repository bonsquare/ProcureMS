<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The APPROPRIATION table is not on every DV template, so the DV prints it only when the Accounting option is ticked. */
class DvAppropriationPrintTest extends TestCase
{
    use RefreshDatabase;

    private function report(bool $include): array
    {
        $organization = Organization::create(['name' => 'ap', 'slug' => 'ap', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'AP', 'name' => 'Appropriation School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-AP-1', 'ors_number' => 'ORS-AP-1',
            'amount' => 500, 'status' => 'approved', 'payee' => 'Supplier', 'purpose' => 'Supplies',
        ]);
        $report->update(['dv_number' => 'DV-AP-1', 'dv_date' => now(), 'payee' => 'Supplier', 'dv_particulars' => 'Supplies', 'payment_mode' => 'MDS Check', 'dv_include_appropriation' => $include]);

        return [$report, $master];
    }

    public function test_the_dv_leaves_out_the_appropriation_table_unless_it_is_ticked(): void
    {
        [$report, $master] = $this->report(false);

        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet no-appropriation"', $html);
        $this->assertStringContainsString('.sheet.no-appropriation .dv-appropriation { display: none; }', $html);
    }

    public function test_the_dv_prints_the_appropriation_table_when_it_is_ticked(): void
    {
        [$report, $master] = $this->report(true);

        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet" data-official-page', $html);
        $this->assertStringNotContainsString('class="sheet no-appropriation"', $html);
        $this->assertStringContainsString('>APPROPRIATION</text>', $html);
    }
}
