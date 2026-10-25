<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\CashReportHistory;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\LiquidationReportLine;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AlphalistService;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlphalistTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(string $code): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin'])];
    }

    private function supplier(School $school, string $name, string $type, ?float $rate1600, ?float $rate0619e): void
    {
        Supplier::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'business_name' => $name, 'tin' => 'TIN-'.$name, 'tax_type' => $type, 'tax_rate' => (float) $rate1600 + (float) $rate0619e, 'tax_rate_1600' => $rate1600, 'tax_rate_0619e' => $rate0619e, 'status' => 'active']);
    }

    private function paid(School $school, string $payee, float $amount, string $date, string $kind = 'goods', float $tax = 100): void
    {
        static $number = 0;
        $number++;
        LiquidationReport::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number, 'ors_kind' => $kind,
            'dv_number' => 'DV-'.$number, 'dv_date' => $date, 'amount' => $amount, 'status' => 'approved', 'payee' => $payee, 'purpose' => 'Purchase', 'source_of_fund' => 'MOOE',
            'dv_deductions' => ['withholding_tax' => $tax], 'payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-'.$number, 'paid_at' => $date,
        ]);
    }

    public function test_the_withholding_tax_of_the_month_is_listed_by_bir_form(): void
    {
        [$school] = $this->tenant('A');
        $this->supplier($school, 'Vat Store', 'vat', 5, 1);
        $this->supplier($school, 'Plain Store', 'non_vat', 3, 1);
        $this->supplier($school, 'Old Store', 'non_vat', null, null);
        $this->paid($school, 'Vat Store', 11200, '2026-10-05');
        $this->paid($school, 'Vat Store', 5600, '2026-10-20');
        $this->paid($school, 'Plain Store', 9000, '2026-10-07', 'direct');
        $this->paid($school, 'Plain Store', 5000, '2026-09-30');
        $this->paid($school, 'Plain Store', 4000, '2026-10-08', 'goods', 0);
        $this->paid($school, 'Old Store', 1000, '2026-10-09');

        $report = app(AlphalistService::class)->build($school, Carbon::parse('2026-10-01'));

        $vat = $report['blocks']['1600VT']->sole();
        $this->assertSame(['WV010', 15000.0, 5.0, 750.0], [$vat['atc'], $vat['base'], $vat['rate'], $vat['withheld']], 'two payments of one payee are added up, VAT base is the amount / 1.12');
        $plain = $report['blocks']['1600PT']->sole();
        $this->assertSame(['WB084', 'Government Payments to Suppliers of Services', 9000.0, 270.0], [$plain['atc'], $plain['nature'], $plain['base'], $plain['withheld']]);
        $this->assertCount(2, $report['blocks']['0619E']);
        $this->assertSame(['Old Store (DV DV-6)'], $report['missing']);
    }

    public function test_the_page_prints_for_the_school_and_refuses_another_organization(): void
    {
        [$school, $user] = $this->tenant('A');
        [, $other] = $this->tenant('B');
        $this->supplier($school, 'Vat Store', 'vat', 5, 1);
        $this->paid($school, 'Vat Store', 11200, '2026-10-05');
        $query = ['school_id' => $school->id, 'month' => '2026-11', 'school_tin' => '449-876-374-485'];

        $this->actingAs($user)->get(route('liquidation.alphalist', $query))->assertOk()
            ->assertSee('MONTHLY ALPHALIST OF PAYEES (MAP)')->assertSee('NOVEMBER 2026')->assertSee('TIN: 449-876-374-485')->assertSee('VAT STORE')->assertSee('500.00');
        $this->actingAs($other)->get(route('liquidation.alphalist', $query))->assertForbidden();
    }

    public function test_every_generation_is_kept_with_one_sheet_per_list(): void
    {
        [$school, $user] = $this->tenant('A');
        [, $other] = $this->tenant('B');
        $this->supplier($school, 'Plain Store', 'non_vat', 3, 1);
        $this->paid($school, 'Plain Store', 9000, '2026-10-07', 'direct');
        $query = ['school_id' => $school->id, 'month' => '2026-11', 'source_of_fund' => 'MOOE'];

        $this->actingAs($user)->post(route('liquidation.alphalist.store'), $query)->assertRedirect();
        $this->post(route('liquidation.alphalist.store'), $query)->assertRedirect();
        $this->assertSame(2, CashReportHistory::where('report', 'alphalist')->count());

        $entry = CashReportHistory::where('report', 'alphalist')->first();
        $this->assertSame('NOVEMBER 2026', $entry->period_text, 'the returned period is the month after the payments');
        $this->assertEquals(360, $entry->total_payments);
        $html = $this->get(route('liquidation.alphalist.history', $entry))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<section class="doc-page"'), 'the 1600PT and 0619E lists are separate sheets');
        $this->get(route('liquidation'))->assertSee(route('liquidation.alphalist.history', $entry), false);
        $this->actingAs($other)->get(route('liquidation.alphalist.history', $entry))->assertNotFound();
    }

    /** Charges the last paid DV to an expense account of the chart (its ORS budget line). */
    private function chargeLastTo(School $school, User $user, string $code): void
    {
        $account = ChartOfAccount::create(['organization_id' => $school->organization_id, 'code' => $code, 'title' => 'Account '.$code, 'category' => 'MOOE']);
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'program' => 'Program', 'source_of_fund' => 'MOOE', 'uacs_code' => $code, 'chart_of_account_id' => $account->id,
            'particulars' => 'Line', 'amount' => 100000, 'q1_amount' => 100000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_by' => $user->id, 'budget_ref_no' => 'BA-'.$code,
        ]);
        LiquidationReport::latest('id')->first()->update(['budget_allocation_id' => $line->id]);
    }

    public function test_a_remittance_dv_made_from_the_alphalist_is_charged_to_the_columns_of_the_payments_it_remits(): void
    {
        [$school, $user] = $this->tenant('A');
        $school->update(['bank_name' => 'LBP Kidapawan']);
        $this->supplier($school, 'Rmr Software', 'non_vat', 3, 2);
        $this->supplier($school, 'Imtech', 'non_vat', 3, 2);
        $this->paid($school, 'Rmr Software', 1050, '2026-10-05', 'direct');
        $this->chargeLastTo($school, $user, '5020503000');
        $this->paid($school, 'Imtech', 9000, '2026-10-06', 'direct');
        $this->chargeLastTo($school, $user, '5029999099');
        // Imtech's ORS is split with a share on Advances for Operating Expenses, which is no column: the tax must still go wholly to the expense.
        $imtech = LiquidationReport::latest('id')->first();
        $advances = BudgetAllocation::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'program' => 'Program', 'source_of_fund' => 'MOOE', 'uacs_code' => '1990101000', 'particulars' => 'Advances', 'amount' => 100000, 'q1_amount' => 100000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-ADV']);
        LiquidationReportLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $imtech->id, 'budget_allocation_id' => $imtech->budget_allocation_id, 'line_no' => 1, 'amount' => 4500]);
        LiquidationReportLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $imtech->id, 'budget_allocation_id' => $advances->id, 'line_no' => 2, 'amount' => 4500]);
        $this->actingAs($user)->post(route('liquidation.alphalist.store'), ['school_id' => $school->id, 'month' => '2026-11', 'school_tin' => '449-876-374-485'])->assertRedirect();
        $history = CashReportHistory::where('report', 'alphalist')->sole();

        $this->post(route('liquidation.alphalist.dv', [$history, '1600PT']))->assertRedirect();
        $this->post(route('liquidation.alphalist.dv', [$history, '1600PT']))->assertRedirect();
        $dv = LiquidationReport::whereNotNull('remittance')->sole();
        $this->assertSame('LBP Kidapawan FAO BIR School A 449-876-374-485 (1600PT)', $dv->payee);
        $this->assertEquals(301.5, $dv->amount, '3% of 1,050 and 9,000');
        $this->assertNull($dv->ors_number);
        $this->assertSame('MOOE', $dv->source_of_fund, 'an alphalist of every fund remits for the fund of its DVs');
        $this->get(route('liquidation'))->assertSee('Create DV 0619E / 1601EQ')->assertDontSee('Create DV 1600PT');

        $dv->update(['dv_number' => 'DV-BIR', 'dv_date' => '2026-11-05', 'payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-BIR', 'paid_at' => '2026-11-05']);
        $register = app(CashReportService::class)->build($school, Carbon::parse('2026-10-01'), Carbon::parse('2026-12-31'));
        $this->assertEquals(['5020503000' => 31.5, '5029999099' => 270.0], $register['lines']->firstWhere('check_no', 'CHK-BIR')['splits']);

        // A remittance DV made before it had a fund still counts in its fund's register, with its payment.
        $dv->update(['source_of_fund' => null]);
        $mooe = app(CashReportService::class)->build($school, Carbon::parse('2026-10-01'), Carbon::parse('2026-12-31'), 'MOOE');
        $this->assertEquals(301.5, $mooe['lines']->firstWhere('check_no', 'CHK-BIR')['amount']);

        $viewer = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->post(route('liquidation.alphalist.dv', [$history, '0619E']))->assertForbidden();
    }
}
