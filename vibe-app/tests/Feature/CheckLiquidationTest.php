<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\CashRegisterCheck;
use App\Models\CashReportHistory;
use App\Models\ChartOfAccount;
use App\Models\CheckLiquidation;
use App\Models\DvBreakdownLine;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CashReportService;
use App\Services\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckLiquidationTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE = '5020301000';

    private const OTHER = '5029999099';

    /** @return array{0: School, 1: User} */
    private function tenant(string $code): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        foreach ([self::OFFICE => 'Office Supplies Expenses', self::OTHER => 'Other Supplies and Materials Expenses', '1990101000' => 'Advances for Operating Expenses', '4030101000' => 'Subsidy'] as $number => $title) {
            ChartOfAccount::create(['organization_id' => $organization->id, 'code' => (string) $number, 'title' => $title, 'category' => 'MOOE']);
        }

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin', 'password' => Hash::make('secret-pass-123')])];
    }

    /** A paid check of the amount whose ORS is charged to the office supplies account. */
    private function check(School $school, User $user, float $amount = 10000, string $number = 'CHK-1'): LiquidationReport
    {
        $account = ChartOfAccount::where('organization_id', $school->organization_id)->where('code', self::OFFICE)->first();
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'program' => 'Program', 'source_of_fund' => 'MOOE', 'uacs_code' => self::OFFICE, 'chart_of_account_id' => $account->id,
            'particulars' => 'Supplies', 'amount' => $amount, 'q1_amount' => $amount, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_by' => $user->id, 'budget_ref_no' => 'BA-'.$number,
        ]);

        return LiquidationReport::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'budget_allocation_id' => $line->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number, 'dv_number' => 'DV-'.$number,
            'dv_date' => '2026-08-05', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Payee', 'purpose' => 'Office supplies', 'source_of_fund' => 'MOOE',
            'payment_mode' => 'MDS Check', 'payment_reference' => $number, 'paid_at' => '2026-08-05',
        ]);
    }

    private function save(User $user, LiquidationReport $check, array $lines, float $refund = 0)
    {
        return $this->actingAs($user)->putJson(route('check-liquidation.save', $check), ['lines' => $lines, 'refund' => $refund]);
    }

    public function test_the_ors_account_is_suggested_and_a_single_account_liquidation_is_approved(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);

        $this->actingAs($user)->getJson(route('check-liquidation.show', $check))->assertOk()
            ->assertJsonPath('source', 'ors_main')->assertJsonPath('lines.0.account_code', self::OFFICE)->assertJsonPath('lines.0.amount', 10000);

        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]])->assertOk();
        $this->actingAs($user)->postJson(route('check-liquidation.approve', $check))->assertOk();

        $this->assertSame('approved', $check->checkLiquidation->status);
        $this->assertNotNull($check->checkLiquidation->approved_at);
        $this->assertTrue(AuditLog::where('action', 'check_liquidation_approved')->exists());
    }

    public function test_a_check_without_an_expense_account_needs_classification(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $check->budgetAllocation->update(['chart_of_account_id' => null, 'uacs_code' => '']);

        $this->actingAs($user)->getJson(route('check-liquidation.show', $check))->assertJsonPath('source', 'needs_classification')->assertJsonPath('lines', []);
    }

    public function test_the_advance_is_split_among_accounts_and_a_repeated_account_is_combined(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);

        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 4000], ['account_code' => self::OTHER, 'amount' => 4000], ['account_code' => self::OFFICE, 'amount' => 2000]])->assertOk();

        $lines = $check->checkLiquidation->lines;
        $this->assertSame([6000.0, 4000.0], $lines->map(fn ($line) => (float) $line->amount)->all());
        $this->assertSame([self::OFFICE, self::OTHER], $lines->pluck('account_code')->all());
    }

    public function test_the_expenses_plus_the_refund_must_equal_the_check(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);

        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 9000]])->assertJsonValidationErrors('lines');
        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 9000]], 1000)->assertOk();
        $this->assertEquals(1000, $check->checkLiquidation->refund_amount);
        $this->assertSame(1, CheckLiquidation::count());
    }

    public function test_every_line_needs_a_valid_expense_account_and_a_positive_amount(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);

        $this->save($user, $check, [['account_code' => '9999999999', 'amount' => 10000]])->assertJsonValidationErrors('lines');
        $this->save($user, $check, [['account_code' => '1990101000', 'amount' => 10000]])->assertJsonValidationErrors('lines');
        $this->save($user, $check, [['account_code' => '4030101000', 'amount' => 10000]])->assertJsonValidationErrors('lines');
        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 12000], ['account_code' => self::OTHER, 'amount' => -2000]])->assertJsonValidationErrors('lines');
        $this->assertSame(0, CheckLiquidation::count());
    }

    public function test_a_retried_approval_changes_nothing_and_an_approved_liquidation_is_locked(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]]);

        $this->postJson(route('check-liquidation.approve', $check))->assertOk();
        $approvedAt = $check->checkLiquidation()->first()->approved_at;
        $this->postJson(route('check-liquidation.approve', $check))->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'check_liquidation_approved')->count());
        $this->assertEquals($approvedAt, $check->checkLiquidation()->first()->approved_at);
        $this->save($user, $check, [['account_code' => self::OTHER, 'amount' => 10000]])->assertJsonValidationErrors('check');
        $this->assertSame(1, CheckLiquidation::count());
        $this->getJson(route('check-liquidation.checks'))->assertJsonCount(0);
    }

    public function test_reopening_needs_a_reason_and_the_password_and_is_audited(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]]);
        $this->postJson(route('check-liquidation.approve', $check));

        $this->postJson(route('check-liquidation.reopen', $check), ['reason' => 'Wrong account', 'password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->postJson(route('check-liquidation.reopen', $check), ['reason' => 'Wrong account', 'password' => 'secret-pass-123'])->assertOk();

        $liquidation = $check->checkLiquidation()->first();
        $this->assertSame('draft', $liquidation->status);
        $this->assertSame(2, $liquidation->version);
        $this->assertTrue(AuditLog::where('action', 'check_liquidation_reopened')->exists());
        $this->save($user, $check, [['account_code' => self::OTHER, 'amount' => 10000]])->assertOk();
    }

    public function test_a_cancelled_or_unpaid_check_cannot_be_liquidated(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $check->update(['paid_at' => null, 'payment_reference' => null]);

        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]])->assertStatus(422);
        $this->actingAs($user)->getJson(route('check-liquidation.checks'))->assertJsonCount(0);
    }

    public function test_cancelling_a_check_with_an_approved_liquidation_is_refused_and_a_draft_is_dropped(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]]);
        $this->postJson(route('check-liquidation.approve', $check));

        $this->post(route('cash.cancel', $check), ['reason' => 'Wrong', 'password' => 'secret-pass-123'])->assertStatus(422);
        $this->assertSame(0, CancelledCheck::count());

        $this->postJson(route('check-liquidation.reopen', $check), ['reason' => 'Cancelling', 'password' => 'secret-pass-123']);
        $this->post(route('cash.cancel', $check), ['reason' => 'Wrong', 'password' => 'secret-pass-123']);
        $this->assertSame(1, CancelledCheck::count());
        $this->assertSame(0, CheckLiquidation::count());
    }

    public function test_another_organization_and_a_user_without_permission_are_refused(): void
    {
        [$school, $user] = $this->tenant('A');
        [, $other] = $this->tenant('B');
        $check = $this->check($school, $user);
        $viewer = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'viewer']);

        $this->actingAs($other)->getJson(route('check-liquidation.show', $check))->assertNotFound();
        $this->putJson(route('check-liquidation.save', $check), ['lines' => [['account_code' => self::OFFICE, 'amount' => 10000]]])->assertNotFound();
        $this->actingAs($viewer)->getJson(route('check-liquidation.show', $check))->assertOk();
        $this->putJson(route('check-liquidation.save', $check), ['lines' => [['account_code' => self::OFFICE, 'amount' => 10000]]])->assertForbidden();
        $this->actingAs($other)->getJson(route('check-liquidation.checks'))->assertJsonCount(0);
        $this->assertSame(0, CheckLiquidation::count());
    }

    private function approvedLiquidation(User $user, LiquidationReport $check, array $lines, float $refund = 0): void
    {
        $this->save($user, $check, $lines, $refund)->assertOk();
        $this->postJson(route('check-liquidation.approve', $check))->assertOk();
    }

    /** @return array<string, array{debit: float, credit: float}> */
    private function ledger(School $school): array
    {
        $totals = [];
        foreach (app(GeneralLedgerService::class)->postings([$school->id], null) as $posting) {
            $totals[$posting['account_code']]['debit'] = ($totals[$posting['account_code']]['debit'] ?? 0) + (float) $posting['debit'];
            $totals[$posting['account_code']]['credit'] = ($totals[$posting['account_code']]['credit'] ?? 0) + (float) $posting['credit'];
        }

        return $totals;
    }

    private function register(School $school): array
    {
        return app(CashReportService::class)->build($school, Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30'));
    }

    public function test_an_approved_liquidation_debits_the_expenses_and_credits_advances_once(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->actingAs($user);
        $this->approvedLiquidation($user, $check, [['account_code' => self::OFFICE, 'amount' => 6000], ['account_code' => self::OTHER, 'amount' => 4000]]);
        $this->postJson(route('check-liquidation.approve', $check))->assertOk();

        $ledger = $this->ledger($school);
        $this->assertEquals(6000, $ledger[self::OFFICE]['debit']);
        $this->assertEquals(4000, $ledger[self::OTHER]['debit']);
        $this->assertEquals(10000, $ledger['1990101000']['credit']);
        $this->assertArrayNotHasKey('1010202024', $ledger, 'Cash is not credited again');

        $this->postJson(route('check-liquidation.reopen', $check), ['reason' => 'Fix', 'password' => 'secret-pass-123'])->assertOk();
        $this->assertArrayNotHasKey(self::OFFICE, $this->ledger($school));
    }

    public function test_a_refund_is_cash_received_back_and_not_an_expense(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->actingAs($user);
        $this->approvedLiquidation($user, $check, [['account_code' => self::OFFICE, 'amount' => 9000]], 1000);
        $this->postJson(route('check-liquidation.approve', $check));

        $this->assertSame(1, CashFundReceipt::where('kind', 'refund')->count());
        $ledger = $this->ledger($school);
        $this->assertEquals(9000, $ledger[self::OFFICE]['debit']);
        $this->assertEquals(1000, $ledger['1010202024']['debit']);
        $this->assertEquals(10000, $ledger['1990101000']['credit']);

        $receipt = CashFundReceipt::where('kind', 'refund')->first();
        $this->delete(route('cash.fund.destroy', $receipt))->assertStatus(422);

        $this->postJson(route('check-liquidation.reopen', $check), ['reason' => 'Fix', 'password' => 'secret-pass-123']);
        $this->assertSame(0, CashFundReceipt::where('kind', 'refund')->count());
    }

    public function test_the_register_puts_an_approved_liquidation_under_its_accounts_and_the_totals_agree(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        $this->actingAs($user);
        $this->approvedLiquidation($user, $check, [['account_code' => self::OFFICE, 'amount' => 6000], ['account_code' => self::OTHER, 'amount' => 3000]], 1000);

        $data = $this->register($school);
        $line = $data['lines']->first();
        $this->assertEquals([self::OFFICE => 6000, self::OTHER => 3000], $line['splits']);
        $this->assertSame('approved', $line['liquidation']);
        $this->assertFalse($data['liquidation']['incomplete']);
        $this->assertEquals(9000, $data['liquidation']['payments']);
        $this->assertEquals(array_sum($line['splits']), $data['liquidation']['classified']);
        $this->get(route('cash.report.register', ['school_id' => $school->id, 'from' => '2026-07-01', 'to' => '2026-09-30']))->assertOk()->assertDontSee('data-incomplete', false);
    }

    public function test_a_check_not_yet_liquidated_is_pending_and_the_final_register_is_blocked(): void
    {
        [$school, $user] = $this->tenant('A');
        $done = $this->check($school, $user, 10000, 'CHK-1');
        $pending = $this->check($school, $user, 5000, 'CHK-2');
        $this->actingAs($user);
        $this->approvedLiquidation($user, $done, [['account_code' => self::OFFICE, 'amount' => 10000]]);
        $this->save($user, $pending, [['account_code' => self::OFFICE, 'amount' => 5000]])->assertOk();

        $data = $this->register($school);
        $this->assertTrue($data['liquidation']['incomplete']);
        $this->assertSame(1, $data['liquidation']['pending']);
        $this->assertEquals(5000, $data['liquidation']['unclassified']);
        $row = $data['lines']->firstWhere('check_no', 'CHK-2');
        $this->assertSame([], $row['splits']);
        $this->assertSame('', $row['uacs_code']);

        foreach (['CHK-1', 'CHK-2'] as $number) {
            CashRegisterCheck::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'source_of_fund' => '', 'quarter_start' => '2026-07-01', 'check_number' => $number, 'created_by' => $user->id]);
        }
        $query = ['school_id' => $school->id, 'from' => '2026-07-01', 'to' => '2026-09-30'];
        $this->get(route('cash.report.register', $query))->assertOk()->assertSee('INCOMPLETE - DRAFT ONLY', false)->assertSee('Pending liquidation');
        $this->postJson(route('cash.report.register.history', $query))->assertStatus(422);
        $this->assertSame(0, CashReportHistory::count());

        $this->approvedLiquidation($user, $pending, [['account_code' => self::OFFICE, 'amount' => 5000]]);
        $this->assertFalse($this->register($school)['liquidation']['incomplete']);
        $this->postJson(route('cash.report.register.history', $query))->assertOk();
    }

    public function test_a_school_that_has_not_started_liquidating_keeps_the_register_as_before(): void
    {
        [$school, $user] = $this->tenant('A');
        $this->check($school, $user);

        $data = $this->register($school);
        $this->assertFalse($data['liquidation']['incomplete']);
        $this->assertSame(self::OFFICE, $data['lines']->first()['uacs_code']);
    }

    public function test_a_cancelled_check_stays_in_the_register_with_no_payment_and_is_not_pending(): void
    {
        [$school, $user] = $this->tenant('A');
        $done = $this->check($school, $user, 10000, 'CHK-1');
        $cancelled = $this->check($school, $user, 3000, 'CHK-2');
        $this->actingAs($user);
        $this->approvedLiquidation($user, $done, [['account_code' => self::OFFICE, 'amount' => 10000]]);
        $this->post(route('cash.cancel', $cancelled), ['reason' => 'Wrong', 'password' => 'secret-pass-123'])->assertRedirect();

        $data = $this->register($school);
        $row = $data['lines']->firstWhere('check_no', 'CHK-2');
        $this->assertTrue($row['cancelled']);
        $this->assertNull($row['amount']);
        $this->assertFalse($data['liquidation']['incomplete']);
    }

    public function test_a_check_whose_dv_already_splits_the_advance_by_expense_account_cannot_be_liquidated(): void
    {
        [$school, $user] = $this->tenant('A');
        $check = $this->check($school, $user);
        DvJournalLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $check->id, 'line_no' => 1, 'account_code' => '1990101000', 'account_title' => 'Advances', 'debit' => 10000, 'credit' => 0]);
        DvBreakdownLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $check->id, 'journal_line_no' => 1, 'line_no' => 1, 'account_code' => self::OFFICE, 'account_title' => 'Office', 'debit' => 10000, 'credit' => 0]);

        $this->save($user, $check, [['account_code' => self::OFFICE, 'amount' => 10000]])->assertJsonValidationErrors('check');
    }
}
