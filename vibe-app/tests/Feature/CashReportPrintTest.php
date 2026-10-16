<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReportPrintTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(string $code, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    public function test_the_reports_tab_shows_the_period_the_fund_entries_and_three_report_buttons(): void
    {
        [$school, $user] = $this->tenant('RT1');

        $html = $this->actingAs($user)->get(route('cash', ['tab' => 'reports']))->assertOk()->getContent();

        $this->assertStringContainsString('Fund received', $html);
        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register'] as $name) {
            $this->assertStringContainsString(e(route($name, ['school_id' => $school->id])), $html, $name);
        }
        $this->assertStringContainsString(route('cash.fund.store'), $html);
    }

    public function test_the_reports_tab_needs_cash_permission(): void
    {
        [, $viewer] = $this->tenant('RT2', 'viewer');

        $this->actingAs($viewer)->get(route('cash', ['tab' => 'reports']))->assertForbidden();
    }

    public function test_a_school_user_cannot_open_another_schools_report(): void
    {
        [, $userA] = $this->tenant('RTA');
        [$schoolB] = $this->tenant('RTB');

        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register'] as $name) {
            $this->actingAs($userA)->get(route($name, ['school_id' => $schoolB->id, 'from' => '2026-07-01', 'to' => '2026-09-30']))->assertForbidden();
        }
    }

    public function test_the_existing_cash_tabs_still_work(): void
    {
        [, $user] = $this->tenant('RT3');

        foreach (['unpaid', 'paid', 'cancelled'] as $tab) {
            $this->actingAs($user)->get(route('cash', ['tab' => $tab]))->assertOk();
        }
    }

    private function paid(School $school, string $check, string $paidAt, float $amount, string $code = '5020399000', string $title = 'Other Supplies Expenses', array $overrides = []): LiquidationReport
    {
        $account = ChartOfAccount::firstOrCreate(['organization_id' => $school->organization_id, 'code' => $code], ['title' => $title, 'category' => 'Expense']);
        $line = BudgetAllocation::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'source_of_fund' => 'MOOE', 'particulars' => 'Line', 'chart_of_account_id' => $account->id, 'amount' => 100000, 'q1_amount' => 25000, 'q2_amount' => 25000, 'q3_amount' => 25000, 'q4_amount' => 25000]);

        return LiquidationReport::create($overrides + [
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-'.$check, 'ors_number' => 'ORS-2026-'.$check, 'dv_number' => 'DV-2026-'.$check,
            'dv_date' => $paidAt, 'responsibility_center_code' => '19-001-01', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Payee '.$check, 'purpose' => 'Purpose '.$check,
            'payment_mode' => 'MDS Check', 'payment_reference' => $check, 'paid_at' => $paidAt, 'budget_allocation_id' => $line->id,
        ]);
    }

    private function periodQuery(School $school, array $extra = []): array
    {
        return ['school_id' => $school->id, 'from' => '2026-07-01', 'to' => '2026-09-30'] + $extra;
    }

    public function test_the_roci_prints_header_lines_and_total(): void
    {
        [$school, $user] = $this->tenant('RO1');
        $school->update(['bank_name' => 'LBP', 'bank_account_number' => '000742-1147-24']);
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Baby Joy I. Carbonell', 'position' => 'Teacher-In-Charge', 'document_role' => 'Disbursing Officer']);
        $this->paid($school, '0001', '2026-09-29', 997.5);
        $this->paid($school, '0002', '2026-09-30', 2.5, '5021299000', 'Janitorial / Other Gen. Services');

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school, ['report_no' => '7'])))->assertOk()
            ->assertSee('REPORT OF CHECKS ISSUED')->assertSee('Appendix 35')->assertSee('School RO1')
            ->assertSee('000742-1147-24')->assertSee('BABY JOY I. CARBONELL')
            ->assertSee('0001')->assertSee('DV-2026-0001')->assertSee('ORS-2026-0001')->assertSee('19-001-01')->assertSee('Payee 0001')
            ->assertSee('5020399000')->assertSee('Other Supplies Expenses')->assertSee('997.50')->assertSee('1,000.00')
            ->assertSee('data-official-page', false);
    }

    public function test_a_cancelled_check_prints_as_cancelled_check_with_no_amount(): void
    {
        [$school, $user] = $this->tenant('RO2');
        $paid = $this->paid($school, '0010', '2026-08-05', 300);
        CancelledCheck::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'liquidation_report_id' => $paid->id, 'ors_number' => 'ORS-2026-0010', 'dv_number' => 'DV-2026-0010', 'check_number' => '0009', 'payment_mode' => 'MDS Check', 'paid_at' => '2026-08-01', 'amount' => 300, 'reason' => 'Wrong amount', 'cancelled_by' => $user->id, 'cancelled_at' => '2026-08-04 10:00:00']);

        $html = $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school)))->assertOk()->getContent();

        $this->assertStringContainsString('Cancelled Check', $html);
        $this->assertStringContainsString('0009', $html);
        $this->assertSame(2, substr_count($html, '300.00'), 'the paid check line and the total only: the cancelled line has no amount');
    }

    public function test_the_roci_prints_with_no_payments_in_the_period(): void
    {
        [$school, $user] = $this->tenant('RO3');

        $html = $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school)))->assertOk()->assertSee('REPORT OF CHECKS ISSUED')->getContent();

        $document = substr($html, (int) strpos($html, '<main'));
        $this->assertStringNotContainsString('NaN', $document);
        $this->assertStringNotContainsString('undefined', $document);
        $this->assertStringContainsString('No check was issued in this period.', $document);
    }

    public function test_ada_payments_are_not_listed_in_the_roci(): void
    {
        [$school, $user] = $this->tenant('RO4');
        $this->paid($school, 'ADA9', '2026-08-05', 300, '5020399000', 'Other Supplies Expenses', ['payment_mode' => 'ADA']);

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school)))->assertOk()->assertDontSee('ADA9');
    }

    public function test_the_record_shows_opening_balance_receipts_checks_ada_and_running_balance(): void
    {
        [$school, $user] = $this->tenant('CA1');
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Baby Joy I. Carbonell', 'position' => 'Teacher-In-Charge', 'document_role' => 'Disbursing Officer']);
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 181500, 'created_by' => $user->id]);
        $this->paid($school, '0001', '2026-07-10', 1000);
        $this->paid($school, 'ADA7', '2026-07-11', 500, '5020301000', 'Office Supplies Expenses', ['payment_mode' => 'ADA']);

        $html = $this->actingAs($user)->get(route('cash.report.checks', $this->periodQuery($school)))->assertOk()
            ->assertSee('CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD')->assertSee('Accountable Officer')->assertSee('BABY JOY I. CARBONELL')
            ->assertSee('data-official-page', false)->getContent();
        $document = substr($html, (int) strpos($html, '<main'));

        $this->assertStringContainsString('181,500.00', $document, 'the opening balance and the NCA received');
        $this->assertStringContainsString('180,500.00', $document, 'balance after the check');
        $this->assertStringContainsString('180,000.00', $document, 'balance after the ADA');
        $this->assertMatchesRegularExpression('/data-col="check"[^>]*>1,000\.00</', $document, 'Check Issued column');
        $this->assertMatchesRegularExpression('/data-col="ada"[^>]*>500\.00</', $document, 'ADA Issued column');
        $this->assertStringContainsString('ADA7', $document);
    }

    public function test_the_record_prints_with_no_payments(): void
    {
        [$school, $user] = $this->tenant('CA2');

        $html = $this->actingAs($user)->get(route('cash.report.checks', $this->periodQuery($school)))->assertOk()->getContent();
        $document = substr($html, (int) strpos($html, '<main'));

        $this->assertStringNotContainsString('NaN', $document);
        $this->assertStringNotContainsString('undefined', $document);
        $this->assertStringContainsString('No check or ADA was issued in this period.', $document);
    }

    private function registerDocument(School $school, User $user): string
    {
        $html = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school)))->assertOk()->assertSee('CASH DISBURSEMENT REGISTER')->getContent();

        return substr($html, (int) strpos($html, '<main'));
    }

    public function test_the_register_has_a_column_per_paid_account_with_its_uacs_code(): void
    {
        [$school, $user] = $this->tenant('CR1');
        $this->paid($school, '0001', '2026-08-01', 1000, '5020101000', 'Traveling Expenses');
        $this->paid($school, '0002', '2026-08-02', 250.5, '5020301000', 'Office Supplies Expense');

        $document = $this->registerDocument($school, $user);

        $this->assertStringContainsString('Traveling Expenses', $document);
        $this->assertStringContainsString('5020301000', $document);
        $this->assertMatchesRegularExpression('/data-account="5020101000"[^>]*>1,000\.00</', $document);
        $this->assertMatchesRegularExpression('/data-account="5020301000"[^>]*>250\.50</', $document);
        $this->assertMatchesRegularExpression('/data-total-account="5020101000"[^>]*>1,000\.00</', $document);
        $this->assertMatchesRegularExpression('/data-col="total-payments"[^>]*>1,250\.50</', $document);
    }

    public function test_the_register_balance_follows_the_payments(): void
    {
        [$school, $user] = $this->tenant('CR2');
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 181500, 'created_by' => $user->id]);
        $this->paid($school, '0001', '2026-08-01', 1000);
        $this->paid($school, '0002', '2026-08-02', 500);

        $document = $this->registerDocument($school, $user);

        $this->assertMatchesRegularExpression('/data-col="advance"[^>]*>181,500\.00</', $document, 'cash advance');
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>180,500\.00</', $document);
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>180,000\.00</', $document);
    }

    public function test_more_than_eight_accounts_continue_in_a_second_table(): void
    {
        [$school, $user] = $this->tenant('CR3');
        foreach (range(1, 9) as $number) {
            $this->paid($school, sprintf('%04d', $number), '2026-08-0'.$number, 100, '50200'.$number.'0000', 'Account '.$number);
        }

        $document = $this->registerDocument($school, $user);

        $this->assertSame(2, substr_count($document, '<table class="report'), 'one table of eight account columns and a second one for the rest');
        $this->assertStringContainsString('Account 9', $document);
    }

    public function test_a_very_long_payee_wraps(): void
    {
        [$school, $user] = $this->tenant('CR4');
        $this->paid($school, '0001', '2026-08-01', 100, '5020301000', 'Office Supplies Expense', ['payee' => 'A very long payee name that has to wrap inside its cell without breaking the register']);

        $document = $this->registerDocument($school, $user);

        $this->assertStringContainsString('A very long payee name that has to wrap', $document);
        $this->assertDoesNotMatchRegularExpression('/<td[^>]*nowrap[^>]*>A very long payee/', $document);
    }

    public function test_the_register_prints_with_no_payments(): void
    {
        [$school, $user] = $this->tenant('CR5');

        $document = $this->registerDocument($school, $user);

        $this->assertStringNotContainsString('NaN', $document);
        $this->assertStringNotContainsString('undefined', $document);
        $this->assertStringContainsString('No payment was made in this period.', $document);
    }
}
