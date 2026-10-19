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

        $this->assertStringContainsString('Bank Reconciliation', $html);
        $this->assertStringNotContainsString('Fund received</h2>', $html);
        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register', 'cash.report.bank'] as $name) {
            $this->assertStringContainsString(e(route($name)), $html, $name);
        }
        $this->assertStringContainsString(str_replace('/', '\/', route('cash.fund.store')), $html);
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

    public function test_the_typed_header_the_chosen_signatories_and_the_remarks_print_and_a_foreign_staff_is_ignored(): void
    {
        [$school, $user] = $this->tenant('RS1');
        [$otherSchool] = $this->tenant('RS2');
        SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Baby Joy I. Carbonell', 'position' => 'Teacher-In-Charge', 'document_role' => 'Disbursing Officer']);
        $chosen = SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => 'Maria Santos', 'position' => 'Cashier']);
        $foreign = SchoolStaff::create(['organization_id' => $otherSchool->organization_id, 'school_id' => $otherSchool->id, 'name' => 'Pedro Outsider', 'position' => 'Clerk']);

        $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['fund_cluster' => 'Trust Fund', 'bank_name' => 'DBP', 'bank_account' => '123-456', 'remarks' => 'For audit', 'officer_id' => $chosen->id, 'received_by_id' => $foreign->id])))->assertOk()
            ->assertSee('Trust Fund')->assertSee('For audit')->assertSee('MARIA SANTOS')->assertDontSee('BABY JOY I. CARBONELL')->assertDontSee('PEDRO OUTSIDER');
    }

    public function test_the_bank_reconciliation_is_only_the_cash_downloaded_and_the_running_balance(): void
    {
        [$school, $user] = $this->tenant('BK1');
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-09-01', 'kind' => 'nca', 'fund_cluster' => 'General Fund', 'amount' => 5000, 'reference' => 'NCA-77', 'created_by' => $user->id]);
        $this->paid($school, '0001', '2026-09-05', 1000);

        $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school)))->assertOk()
            ->assertSee('BANK RECONCILIATION')->assertSee('NCA-77')->assertSee('5,000.00')->assertSee('4,000.00')
            ->assertDontSee('Balance per bank statement')->assertDontSee('PREPARED BY')->assertDontSee('Fund Cluster');
    }

    public function test_the_balance_before_the_period_is_the_beginning_balance_of_the_other_reports(): void
    {
        [$school, $user] = $this->tenant('BK3');
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-06-15', 'kind' => 'nca', 'fund_cluster' => 'General Fund', 'amount' => 10000, 'created_by' => $user->id]);
        $this->paid($school, '0001', '2026-06-20', 2500);
        $this->paid($school, '0002', '2026-09-05', 1000);

        $register = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school)))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>7,500\.00</', $register, 'the working fund starts from what was left on 30 June');
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>6,500\.00</', $register);

        $checks = $this->actingAs($user)->get(route('cash.report.checks', $this->periodQuery($school)))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>7,500\.00</', $checks);
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>6,500\.00</', $checks);

        $bank = $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school)))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>7,500\.00</', $bank);
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>6,500\.00</', $bank);
    }

    public function test_a_balance_forwarded_can_be_recorded_and_counts_as_cash_downloaded(): void
    {
        [$school, $user] = $this->tenant('BF1');
        $this->actingAs($user)->postJson(route('cash.fund.store'), ['school_id' => $school->id, 'received_at' => '2026-09-01', 'kind' => 'balance_forwarded', 'source_of_fund' => 'MOOE', 'amount' => 3000])->assertOk();

        $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school)))->assertOk()->assertSee('Balance forwarded')->assertSee('3,000.00');
    }

    public function test_a_recorded_balance_forwarded_is_shown_at_the_top_with_its_fund_and_not_as_cash_downloaded(): void
    {
        [$school, $user] = $this->tenant('BK8');
        $this->actingAs($user)->postJson(route('cash.fund.store'), ['school_id' => $school->id, 'received_at' => '2026-09-01', 'kind' => 'balance_forwarded', 'source_of_fund' => 'Beginning balance', 'amount' => 500])->assertOk();
        $this->actingAs($user)->postJson(route('cash.fund.store'), ['school_id' => $school->id, 'received_at' => '2026-09-02', 'kind' => 'nca', 'source_of_fund' => 'MOOE', 'amount' => 1000])->assertOk();

        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-06-10', 'kind' => 'nca', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 9000, 'created_by' => $user->id]);

        $html = $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school)))->assertOk()->assertSee('Beginning balance')->getContent();
        $this->assertSame(1, substr_count($html, 'Balance forwarded'), 'the automatic balance forwarded is left out when one was recorded');
        $this->assertMatchesRegularExpression('/Sep 01, 2026<\/td><td colspan="6">Balance forwarded, Beginning balance/', $html);
        $this->assertMatchesRegularExpression('/class="total">.*?<td class="num">1,000\.00<\/td><td class="num">0\.00<\/td><td class="num">1,500\.00</s', $html);
    }

    public function test_a_cash_report_of_one_source_of_fund_keeps_the_whole_series_of_checks_but_only_that_funds_amounts(): void
    {
        [$school, $user] = $this->tenant('BK7');
        $this->paid($school, '0101', '2026-08-05', 1000, overrides: ['source_of_fund' => 'MOOE']);
        $this->paid($school, '0202', '2026-08-06', 2000, overrides: ['source_of_fund' => 'ARAL']);
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-07-02', 'kind' => 'nca', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 5000, 'created_by' => $user->id]);
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-07-03', 'kind' => 'nca', 'source_of_fund' => 'ARAL', 'fund_cluster' => 'General Fund', 'amount' => 7000, 'created_by' => $user->id]);

        $register = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['source_of_fund' => 'MOOE'])))->assertOk()
            ->assertSee('0101')->assertSee('0202')->assertDontSee('Source of Fund')->assertDontSee('2,000.00')->assertDontSee('CANCELLED')->getContent();
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>4,000\.00</', $register);
        foreach (['cash.report.roci', 'cash.report.checks'] as $name) {
            $this->actingAs($user)->get(route($name, $this->periodQuery($school, ['source_of_fund' => 'MOOE'])))->assertOk()->assertSee('0101')->assertSee('0202')->assertDontSee('2,000.00');
        }
        $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school)))->assertOk()->assertSee('0101')->assertSee('0202')->assertDontSee('Source of Fund:');
    }

    public function test_each_source_of_fund_has_its_own_beginning_balance_and_a_recorded_balance_forwarded_replaces_the_carried_over_one(): void
    {
        [$school, $user] = $this->tenant('BK6');
        foreach ([['2026-06-10', 'nca', 'MOOE', 9000], ['2026-06-11', 'nca', 'ARAL', 4000], ['2026-09-01', 'balance_forwarded', 'MOOE', 500]] as [$date, $kind, $fund, $amount]) {
            CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => $date, 'kind' => $kind, 'source_of_fund' => $fund, 'fund_cluster' => 'General Fund', 'amount' => $amount, 'created_by' => $user->id]);
        }

        $aral = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['source_of_fund' => 'ARAL'])))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Beginning Balance[^<]*<\/td><td class="num" data-col="advance">4,000\.00/', $aral, 'ARAL begins with its own downloaded cash');

        $moooe = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['source_of_fund' => 'MOOE'])))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/Beginning Balance[^<]*<\/td><td class="num" data-col="advance">9,/', $moooe, 'the recorded balance forwarded replaces the carried-over balance');
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>500\.00</', $moooe);

        $bank = $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school, ['source_of_fund' => 'MOOE'])))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-col="balance"[^>]*>500\.00</', $bank);
        $this->assertStringNotContainsString('9,000.00', $bank);
    }

    public function test_the_register_names_the_fund_the_kind_and_the_quarter_of_the_cash_advance(): void
    {
        [$school, $user] = $this->tenant('BK5');
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-08-03', 'kind' => 'cash_advance', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 145750, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['source_of_fund' => 'MOOE'])))->assertOk()->assertSee('MOOE Cash Advance 3rd qrtr. 2026')->assertSee('145,750.00');
    }

    public function test_the_bank_reconciliation_names_the_fund_of_each_downloaded_amount(): void
    {
        [$school, $user] = $this->tenant('BK9');
        foreach ([['2026-06-10', 'MOOE', 4000], ['2026-06-12', 'ARAL', 2500], ['2026-09-01', 'SBFP', 1500]] as [$date, $fund, $amount]) {
            CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => $date, 'kind' => 'nca', 'source_of_fund' => $fund, 'fund_cluster' => 'General Fund', 'amount' => $amount, 'created_by' => $user->id]);
        }

        $this->actingAs($user)->get(route('cash.report.bank', $this->periodQuery($school)))->assertOk()
            ->assertSee('ARAL, MOOE, SBFP')->assertSee('Balance forwarded')->assertSee('6,500.00')->assertSee('1,500.00');
    }

    public function test_the_downloaded_cash_is_listed_and_saved_as_json_for_the_window(): void
    {
        [$school, $user] = $this->tenant('BK2');
        $this->actingAs($user)->postJson(route('cash.fund.store'), ['school_id' => $school->id, 'received_at' => '2026-10-02', 'kind' => 'opening_balance', 'source_of_fund' => 'MOOE', 'amount' => 750.5])->assertOk();

        $this->actingAs($user)->getJson(route('cash.fund.index', ['school_id' => $school->id, 'from' => '2026-10-01', 'to' => '2026-12-31']))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.amount_label', '750.50')->assertJsonPath('0.kind_label', 'Opening balance')->assertJsonPath('0.source_of_fund', 'MOOE');
        $this->actingAs($user)->getJson(route('cash.fund.index', ['school_id' => $school->id, 'from' => '2027-01-01', 'to' => '2027-03-31']))->assertOk()->assertJsonCount(0);
    }

    public function test_the_roci_report_number_defaults_to_year_month_and_sequence_and_the_sheet_number_counts_pages(): void
    {
        [$school, $user] = $this->tenant('RN1');

        $html = $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school)))->assertOk()->getContent();
        $this->assertStringContainsString(now()->format('Y-m').'-001', $html);
        $this->assertStringContainsString('data-sheet-no', $html);

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school, ['report_no' => '2026-10-007', 'sheet_no' => '2 of 3'])))->assertOk()->assertSee('2026-10-007')->assertSee('2 of 3');
    }

    public function test_a_signatory_who_is_not_staff_can_be_typed_by_hand(): void
    {
        [$school, $user] = $this->tenant('MN1');

        $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['received_by_id' => 'manual', 'received_by_manual_name' => 'Honey Grace R. Ofalsa', 'received_by_manual_position' => 'Administrative Assistant III', 'officer_id' => 'manual', 'officer_manual_name' => 'Typed Officer', 'officer_manual_position' => 'School Head'])))->assertOk()
            ->assertSee('HONEY GRACE R. OFALSA')->assertSee('Administrative Assistant III')->assertSee('TYPED OFFICER')->assertSee('School Head');

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school, ['officer_id' => 'manual', 'officer_manual_name' => 'Roci Typed'])))->assertOk()->assertSee('ROCI TYPED');
        $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school, ['received_by_id' => 'manual'])))->assertOk();
    }

    public function test_the_checks_and_advices_prints_the_typed_header_and_officer(): void
    {
        [$school, $user] = $this->tenant('CK1');

        $this->actingAs($user)->get(route('cash.report.checks', $this->periodQuery($school, ['entity_name' => 'Typed Entity', 'station' => 'Typed Station', 'officer_name' => 'Typed Officer', 'officer_position' => 'Principal I', 'sheet_no' => '2 of 3'])))->assertOk()
            ->assertSee('CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD')->assertSee('TYPED ENTITY')->assertSee('TYPED STATION')->assertSee('TYPED OFFICER')->assertSee('Principal I')->assertSee('2 of 3')->assertSee('CERTIFICATION');
    }

    public function test_the_typed_bank_prints_on_the_roci(): void
    {
        [$school, $user] = $this->tenant('RB1');

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school, ['bank_name' => 'DBP', 'bank_account' => '123-456'])))->assertOk()->assertSee('DBP / 123-456');
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

    public function test_every_account_gets_a_column_in_one_table_however_many_there_are(): void
    {
        [$school, $user] = $this->tenant('CR3');
        foreach (range(1, 10) as $number) {
            $this->paid($school, sprintf('%04d', $number), '2026-08-'.sprintf('%02d', $number), 100, '502'.sprintf('%02d', $number).'00000', 'Account '.$number);
        }

        $document = $this->registerDocument($school, $user);

        $this->assertSame(1, substr_count($document, '<table class="report'), 'one table, with a column for every account');
        $this->assertSame(10, substr_count($document, 'data-total-account='));
        $this->assertStringContainsString('Account 10', $document);
        $this->assertStringContainsString('report dense', $document, 'the columns narrow when there are more than nine');
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

    public function test_others_payments_and_cancelled_adas_stay_out_of_the_roci_and_the_bank_balance_of_the_record(): void
    {
        [$school, $user] = $this->tenant('CX1');
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 10000, 'created_by' => $user->id]);
        $this->paid($school, 'OR-123', '2026-08-05', 300, '5020301000', 'Office Supplies Expenses', ['payment_mode' => 'Others']);
        $ada = $this->paid($school, 'ADA3', '2026-08-06', 200, '5020301000', 'Office Supplies Expenses', ['payment_mode' => 'ADA']);
        CancelledCheck::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'liquidation_report_id' => $ada->id, 'ors_number' => 'ORS-2026-ADA3', 'dv_number' => 'DV-2026-ADA3', 'check_number' => 'ADA-OLD', 'payment_mode' => 'ADA', 'paid_at' => '2026-08-01', 'amount' => 200, 'reason' => 'Wrong amount', 'cancelled_by' => $user->id, 'cancelled_at' => '2026-08-04 10:00:00']);

        $this->actingAs($user)->get(route('cash.report.roci', $this->periodQuery($school)))->assertOk()->assertDontSee('OR-123')->assertDontSee('ADA-OLD')->assertDontSee('ADA3');

        $record = $this->actingAs($user)->get(route('cash.report.checks', $this->periodQuery($school)))->assertOk()->assertDontSee('OR-123')->getContent();
        $document = substr($record, (int) strpos($record, '<main'));
        $this->assertStringContainsString('9,800.00', $document, 'the bank balance follows the ADA only (10,000 - 200), not the cash payment');
        $this->assertStringNotContainsString('9,500.00', $document);

        $register = $this->actingAs($user)->get(route('cash.report.register', $this->periodQuery($school)))->assertOk()->assertSee('OR-123')->getContent();
        $this->assertStringContainsString('9,500.00', substr($register, (int) strpos($register, '<main')), 'the working fund pays the cash payment too');
    }
}
