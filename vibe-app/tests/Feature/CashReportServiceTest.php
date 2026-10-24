<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\DvBreakdownLine;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private School $school;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'Cr', 'slug' => 'cr', 'status' => 'active']);
        $this->school = School::create(['organization_id' => $this->organization->id, 'code' => 'CRS', 'name' => 'Report School', 'status' => 'active']);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'role' => 'school_admin']);
        $this->actingAs($this->user);
    }

    private function account(string $code, string $title): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(['organization_id' => $this->organization->id, 'code' => $code], ['title' => $title, 'category' => 'Expense']);
    }

    /** A paid ORS and DV charged to a budget line with the account, or without one. */
    private function paid(string $check, string $paidAt, float $amount, ?ChartOfAccount $account, array $overrides = [], ?School $school = null): LiquidationReport
    {
        $school ??= $this->school;
        $line = BudgetAllocation::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'source_of_fund' => 'MOOE', 'particulars' => 'Line', 'chart_of_account_id' => $account?->id, 'amount' => 100000, 'q1_amount' => 25000, 'q2_amount' => 25000, 'q3_amount' => 25000, 'q4_amount' => 25000]);

        return LiquidationReport::create($overrides + [
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-'.$check, 'ors_number' => 'ORS-2026-'.$check, 'dv_number' => 'DV-2026-'.$check,
            'dv_date' => $paidAt, 'responsibility_center_code' => '19-001-01', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Payee '.$check, 'purpose' => 'Purpose '.$check,
            'payment_mode' => 'MDS Check', 'payment_reference' => $check, 'paid_at' => $paidAt, 'budget_allocation_id' => $line->id,
        ]);
    }

    private function build(string $from = '2026-07-01', string $to = '2026-09-30', ?School $school = null): array
    {
        return app(CashReportService::class)->build($school ?? $this->school, Carbon::parse($from), Carbon::parse($to));
    }

    public function test_paid_dvs_of_the_period_become_check_lines_with_uacs_and_nature(): void
    {
        $account = $this->account('5020399000', 'Other Supplies Expenses');
        $this->paid('0001', '2026-09-29', 997.5, $account);

        $report = $this->build();
        $line = $report['lines']->first();

        $this->assertCount(1, $report['lines']);
        $this->assertSame('0001', $line['check_no']);
        $this->assertSame('DV-2026-0001', $line['dv_no']);
        $this->assertSame('ORS-2026-0001', $line['ors_no']);
        $this->assertSame('19-001-01', $line['responsibility_center']);
        $this->assertSame('Payee 0001', $line['payee']);
        $this->assertSame('5020399000', $line['uacs_code']);
        $this->assertSame('Other Supplies Expenses', $line['nature']);
        $this->assertSame(997.5, $line['amount']);
        $this->assertSame('check', $line['mode']);
        $this->assertSame('2026-09-29', $line['dv_date']?->toDateString());
        $this->assertFalse($line['cancelled']);
        $this->assertSame(997.5, $report['total_paid']);
    }

    public function test_a_dv_with_withholding_tax_pays_the_net_and_keeps_the_gross_for_the_expense(): void
    {
        $account = $this->account('5020399000', 'Other Supplies Expenses');
        $this->paid('0001', '2026-09-29', 10000, $account, ['dv_deductions' => ['withholding_tax' => 500, 'other' => [['label' => 'Penalty', 'amount' => 100]]]]);
        $this->paid('0002', '2026-09-30', 2000, $account);

        $report = $this->build();
        $withTax = $report['lines']->first();
        $plain = $report['lines']->last();

        $this->assertSame(9400.0, $withTax['amount']);
        $this->assertSame(10000.0, $withTax['gross']);
        $this->assertSame(600.0, $withTax['tax']);
        $this->assertSame(2000.0, $plain['amount']);
        $this->assertSame(0.0, $plain['tax']);
        $this->assertSame(11400.0, $report['total_paid']);
        $this->assertSame(-11400.0, $report['ending']);
    }

    public function test_the_running_balance_is_opening_plus_receipts_minus_payments(): void
    {
        $account = $this->account('5020301000', 'Office Supplies Expenses');
        CashFundReceipt::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 181500, 'created_by' => $this->user->id]);
        $this->paid('0001', '2026-07-10', 1000, $account);
        $this->paid('0002', '2026-07-11', 500, $account);

        $report = $this->build();

        $this->assertSame([180500.0, 180000.0], $report['lines']->pluck('balance')->all());
        $this->assertSame(181500.0, $report['total_received']);
        $this->assertSame(180000.0, $report['ending']);
        $this->assertSame(0.0, $report['opening']);
    }

    public function test_the_balance_of_an_earlier_period_is_carried_forward(): void
    {
        $account = $this->account('5020301000', 'Office Supplies Expenses');
        CashFundReceipt::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'received_at' => '2026-04-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 10000, 'created_by' => $this->user->id]);
        $this->paid('0001', '2026-05-10', 2500, $account);
        CashFundReceipt::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 7500, 'created_by' => $this->user->id]);

        $report = $this->build();

        $this->assertSame(7500.0, $report['opening'], 'what was downloaded before the period less what was paid before it');
        $this->assertSame(7500.0, $report['opening_bank']);
        $this->assertSame(15000.0, $report['ending'], 'the carried balance plus the cash downloaded in this quarter');
    }

    public function test_a_cancelled_ada_is_an_ada_line_not_a_check(): void
    {
        $account = $this->account('5020301000', 'Office Supplies Expenses');
        $paid = $this->paid('ADA2', '2026-08-05', 300, $account, ['payment_mode' => 'ADA']);
        CancelledCheck::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'liquidation_report_id' => $paid->id, 'ors_number' => 'ORS-2026-ADA2', 'dv_number' => 'DV-2026-ADA2', 'check_number' => 'ADA-01', 'payment_mode' => 'ADA', 'paid_at' => '2026-08-01', 'amount' => 300, 'reason' => 'Wrong amount', 'cancelled_by' => $this->user->id, 'cancelled_at' => '2026-08-04 10:00:00']);

        $this->assertSame(['ada', 'ada'], $this->build()['lines']->pluck('mode')->all());
    }

    public function test_an_others_payment_is_neither_a_check_nor_an_ada(): void
    {
        $this->paid('OR-123', '2026-08-05', 300, $this->account('5020301000', 'Office Supplies Expenses'), ['payment_mode' => 'Others']);

        $line = $this->build()['lines']->first();

        $this->assertSame('other', $line['mode']);
        $this->assertSame(300.0, $line['amount'], 'it still counts as a payment of the working fund');
    }

    public function test_a_cancelled_check_is_a_zero_line_and_the_new_payment_is_a_second_line(): void
    {
        $account = $this->account('5020301000', 'Office Supplies Expenses');
        $paid = $this->paid('0010', '2026-08-05', 300, $account);
        CancelledCheck::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'liquidation_report_id' => $paid->id, 'ors_number' => 'ORS-2026-0010', 'dv_number' => 'DV-2026-0010', 'check_number' => '0009', 'payment_mode' => 'MDS Check', 'paid_at' => '2026-08-01', 'amount' => 300, 'reason' => 'Wrong amount', 'cancelled_by' => $this->user->id, 'cancelled_at' => '2026-08-04 10:00:00']);

        $report = $this->build();

        $this->assertSame(['0009', '0010'], $report['lines']->pluck('check_no')->all());
        $this->assertTrue($report['lines'][0]['cancelled']);
        $this->assertSame('Cancelled Check', $report['lines'][0]['payee']);
        $this->assertNull($report['lines'][0]['amount']);
        $this->assertSame(300.0, $report['total_paid']);
    }

    public function test_ada_payments_are_marked_ada(): void
    {
        $this->paid('ADA1', '2026-08-05', 300, null, ['payment_mode' => 'ADA']);

        $this->assertSame('ada', $this->build()['lines']->first()['mode']);
    }

    public function test_unpaid_and_other_periods_and_other_schools_are_left_out(): void
    {
        $account = $this->account('5020301000', 'Office Supplies Expenses');
        $this->paid('0001', '2026-10-02', 100, $account);
        $this->paid('0002', '2026-07-05', 100, $account, ['paid_at' => null, 'payment_reference' => null]);
        $otherSchool = School::create(['organization_id' => $this->organization->id, 'code' => 'OTH', 'name' => 'Other School', 'status' => 'active']);
        $this->paid('0003', '2026-08-05', 100, $account, [], $otherSchool);

        $this->assertCount(0, $this->build()['lines']);
    }

    public function test_the_uacs_falls_back_to_the_dv_debit_line_when_the_budget_line_has_no_account(): void
    {
        $report = $this->paid('0001', '2026-08-05', 700, null);
        DvJournalLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $report->id, 'line_no' => 1, 'account_code' => '5020402000', 'account_title' => 'Electricity Expenses', 'debit' => 700, 'credit' => 0]);
        DvJournalLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $report->id, 'line_no' => 2, 'account_code' => '1010404000', 'account_title' => 'Cash - MDS', 'debit' => 0, 'credit' => 700]);

        $line = $this->build()['lines']->first();

        $this->assertSame('5020402000', $line['uacs_code']);
        $this->assertSame('Electricity Expenses', $line['nature']);
    }

    public function test_an_empty_period_returns_no_lines_and_no_errors(): void
    {
        $report = $this->build();

        $this->assertCount(0, $report['lines']);
        $this->assertSame(0.0, $report['ending']);
        $this->assertSame([], $report['accounts']);
    }

    public function test_accounts_are_those_of_the_sob_of_the_period_and_the_fund(): void
    {
        $aip = Aip::create(['organization_id' => $this->organization->id, 'school_id' => $this->school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => 'approved']);
        $kra = AipKra::create(['organization_id' => $this->organization->id, 'aip_id' => $aip->id, 'pillar' => 'Access', 'kra' => 'KRA', 'program' => 'Program']);
        $activity = AipActivity::create(['organization_id' => $this->organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Activity', 'physical_target' => 1]);
        foreach ([[3, 'MOOE', '5020402000', 'Electricity Expenses'], [3, 'MOOE', '5020101000', 'Traveling Expenses'], [3, 'ARAL', '5021304000', 'Repairs'], [4, 'MOOE', '5020301000', 'Office Supplies Expenses']] as [$quarter, $fund, $code, $title]) {
            $plan = SobPlan::firstOrCreate(['school_id' => $this->school->id, 'fiscal_year' => 2026, 'quarter' => $quarter, 'fund_source' => $fund], ['organization_id' => $this->organization->id, 'aip_id' => $aip->id, 'status' => 'approved']);
            SobItem::create(['organization_id' => $this->organization->id, 'sob_plan_id' => $plan->id, 'aip_activity_id' => $activity->id, 'chart_of_account_id' => $this->account($code, $title)->id, 'particulars' => $title, 'quantity' => 1, 'unit' => 'lot', 'unit_cost' => 1, 'amount' => 1]);
        }

        $service = app(CashReportService::class);
        $third = [Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30')];

        $this->assertSame(['5020101000' => 'Traveling Expenses', '5020402000' => 'Electricity Expenses', '5021304000' => 'Repairs'], $service->build($this->school, ...$third)['accounts']);
        $this->assertSame(['5020101000' => 'Traveling Expenses', '5020402000' => 'Electricity Expenses'], $service->build($this->school, ...$third, fund: 'MOOE')['accounts']);
        $this->assertSame(['5020301000' => 'Office Supplies Expenses'], $service->build($this->school, Carbon::parse('2026-10-01'), Carbon::parse('2026-12-31'), 'MOOE')['accounts']);
    }

    /** @return array<string, float> the split of the line of a check */
    private function splitOf(string $check, string $from = '2026-07-01', string $to = '2026-09-30'): array
    {
        return array_map('floatval', $this->build($from, $to)['lines']->firstWhere('check_no', $check)['splits']);
    }

    private function bir(): ChartOfAccount
    {
        return $this->account('2020101000', 'Due to BIR');
    }

    public function test_a_remittance_takes_the_withheld_taxes_oldest_first(): void
    {
        $this->paid('0001', '2026-07-05', 1000, $this->account('5020399000', 'Other Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 100]]);
        $this->paid('0002', '2026-07-10', 2000, $this->account('5020301000', 'Office Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 200]]);
        $this->paid('0003', '2026-07-20', 250, $this->bir());
        $this->paid('0004', '2026-07-25', 50, $this->bir());

        $this->assertSame(['5020399000' => 100.0, '5020301000' => 150.0], $this->splitOf('0003'));
        $this->assertSame(['5020301000' => 50.0], $this->splitOf('0004'));
    }

    public function test_a_remittance_with_a_breakdown_keeps_it(): void
    {
        $this->paid('0001', '2026-07-05', 1000, $this->account('5020399000', 'Other Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 100]]);
        $remittance = $this->paid('0002', '2026-07-20', 100, $this->bir());
        DvJournalLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $remittance->id, 'line_no' => 1, 'account_code' => '2020101000', 'account_title' => 'Due to BIR', 'debit' => 100, 'credit' => 0]);
        foreach ([[1, '2020101000', 100, 0], [2, '5020301000', 100, 0], [3, '2020101000', 0, 100]] as [$no, $code, $debit, $credit]) {
            DvBreakdownLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $remittance->id, 'journal_line_no' => 1, 'line_no' => $no, 'account_code' => $code, 'account_title' => 'Account', 'debit' => $debit, 'credit' => $credit]);
        }

        $this->assertSame(['5020301000' => 100.0], $this->splitOf('0002'));
    }

    public function test_a_remittance_with_no_withheld_tax_has_no_split_and_one_larger_than_the_tax_gets_the_tax_accounts(): void
    {
        $this->paid('0003', '2026-07-20', 300, $this->bir());
        $this->assertSame([], $this->splitOf('0003'));

        $this->paid('0001', '2026-07-05', 1000, $this->account('5020399000', 'Other Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 100]]);
        $this->assertSame(['5020399000' => 100.0], $this->splitOf('0003'));
    }

    public function test_a_remittance_of_a_later_quarter_uses_a_tax_withheld_earlier(): void
    {
        $this->paid('0001', '2026-06-10', 1000, $this->account('5020399000', 'Other Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 100]]);
        $this->paid('0002', '2026-08-05', 100, $this->bir());

        $report = $this->build('2026-07-01', '2026-09-30');
        $this->assertSame(['0002'], $report['lines']->pluck('check_no')->all());
        $this->assertSame(['5020399000' => 100.0], $this->splitOf('0002'));
    }

    public function test_a_tax_of_a_dv_charged_to_several_accounts_is_split_in_proportion(): void
    {
        $dv = $this->paid('0001', '2026-07-05', 1000, $this->account('5020399000', 'Other Supplies Expenses'), ['dv_deductions' => ['withholding_tax' => 100]]);
        $this->account('5020301000', 'Office Supplies Expenses');
        DvJournalLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $dv->id, 'line_no' => 1, 'account_code' => '5020399000', 'account_title' => 'Other Supplies Expenses', 'debit' => 1000, 'credit' => 0]);
        foreach ([[1, '5020399000', 600], [2, '5020301000', 400]] as [$no, $code, $debit]) {
            DvBreakdownLine::create(['organization_id' => $this->organization->id, 'liquidation_report_id' => $dv->id, 'journal_line_no' => 1, 'line_no' => $no, 'account_code' => $code, 'account_title' => 'Account', 'debit' => $debit, 'credit' => 0]);
        }
        $this->paid('0002', '2026-07-20', 100, $this->bir());

        $this->assertSame(['5020399000' => 60.0, '5020301000' => 40.0], $this->splitOf('0002'));
    }
}
