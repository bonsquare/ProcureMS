<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
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

    public function test_accounts_lists_only_accounts_paid_in_the_period(): void
    {
        $this->paid('0001', '2026-08-05', 100, $this->account('5020402000', 'Electricity Expenses'));
        $this->paid('0002', '2026-08-06', 100, $this->account('5020101000', 'Traveling Expenses'));
        $this->paid('0003', '2026-12-06', 100, $this->account('5020301000', 'Office Supplies Expenses'));

        $this->assertSame(['5020101000' => 'Traveling Expenses', '5020402000' => 'Electricity Expenses'], $this->build()['accounts']);
    }
}
