<?php

namespace Tests\Feature;

use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\DvBreakdownLine;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\OpeningBalance;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    private const CASH = '1010202024';

    private const SURPLUS = '3010101000';

    private const EXPENSE = '5020399000';

    /** @return array{0: School, 1: User} */
    private function tenant(string $code, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    /** An approved DV with a journal of Dr expense / Cr cash. */
    private function dv(School $school, string $number, string $date, float $debit = 8000, float $credit = 8000, array $overrides = []): LiquidationReport
    {
        $report = LiquidationReport::create($overrides + [
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number, 'dv_number' => 'DV-'.$number,
            'dv_date' => $date, 'amount' => $debit, 'status' => 'approved', 'payee' => 'Payee '.$number, 'purpose' => 'Purpose '.$number,
        ]);
        DvJournalLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $report->id, 'line_no' => 1, 'account_code' => self::EXPENSE, 'account_title' => 'Other Supplies Expenses', 'debit' => $debit, 'credit' => 0]);
        DvJournalLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $report->id, 'line_no' => 2, 'account_code' => self::CASH, 'account_title' => 'Cash in Bank', 'debit' => 0, 'credit' => $credit]);

        return $report;
    }

    /** Opening Dr cash / Cr surplus 10,000; NCA 50,000 on 1 Feb; DV of 8,000 on 1 Mar. */
    private function books(string $code = 'FR1'): array
    {
        [$school, $user] = $this->tenant($code);
        foreach ([[self::CASH, 'Cash in Bank', 10000, 0], [self::SURPLUS, 'Accumulated Surplus/(Deficit)', 0, 10000]] as [$account, $title, $debit, $credit]) {
            OpeningBalance::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fund_cluster' => 'General Fund', 'as_of' => '2026-01-01', 'account_code' => $account, 'account_title' => $title, 'debit' => $debit, 'credit' => $credit]);
        }
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-02-01', 'kind' => 'nca', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 50000, 'reference' => 'NCA-1', 'created_by' => $user->id]);
        $this->dv($school, '0001', '2026-03-01');

        return [$school, $user];
    }

    private function service(): GeneralLedgerService
    {
        return new GeneralLedgerService;
    }

    public function test_postings_include_opening_receipt_and_approved_dv(): void
    {
        [$school] = $this->books();

        $postings = $this->service()->postings([$school->id], null);

        $this->assertSame(['opening' => 2, 'receipt' => 2, 'dv' => 2], $postings->countBy('source')->all());
        $this->assertSame(68000.0, round($postings->sum(fn ($p) => (float) $p['debit']), 2));
        $this->assertSame(68000.0, round($postings->sum(fn ($p) => (float) $p['credit']), 2));
    }

    public function test_unapproved_dvs_are_not_posted(): void
    {
        [$school] = $this->tenant('FR2');
        $this->dv($school, '0002', '2026-03-01', 100, 100, ['status' => 'for_review']);
        $this->dv($school, '0003', '2026-03-01', 100, 100, ['dv_number' => null]);

        $this->assertCount(0, $this->service()->postings([$school->id], null));
    }

    public function test_receipts_of_kind_opening_balance_and_balance_forwarded_are_not_posted(): void
    {
        [$school, $user] = $this->tenant('FR3');
        foreach (['opening_balance', 'balance_forwarded'] as $kind) {
            CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-02-01', 'kind' => $kind, 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 900, 'created_by' => $user->id]);
        }

        $this->assertCount(0, $this->service()->postings([$school->id], null));
    }

    public function test_postings_respect_the_to_date_and_fund_cluster_filters(): void
    {
        [$school] = $this->books('FR4');
        $this->dv($school, '0009', '2026-03-05', 10, 10, ['dv_fund_cluster' => 'Special Fund']);

        $this->assertCount(6, $this->service()->postings([$school->id], 'General Fund', Carbon::parse('2026-03-31')));
        $this->assertCount(4, $this->service()->postings([$school->id], null, Carbon::parse('2026-02-15')));
        $this->assertCount(2, $this->service()->postings([$school->id], 'Special Fund'));
    }

    private function range(): array
    {
        return [Carbon::parse('2026-02-01'), Carbon::parse('2026-12-31')];
    }

    public function test_trial_balance_has_equal_totals_and_correct_cash_row(): void
    {
        [$school] = $this->books('FR5');

        $tb = $this->service()->trialBalance([$school->id], null, ...$this->range());

        $cash = $tb['rows']->firstWhere('account_code', self::CASH);
        $this->assertSame(['10000.00', '50000.00', '8000.00', '52000.00'], [$cash['opening_debit'], $cash['period_debit'], $cash['period_credit'], $cash['ending_debit']]);
        $this->assertTrue($tb['balanced']);
        $this->assertSame(['debit' => '60000.00', 'credit' => '60000.00'], $tb['totals']);
    }

    public function test_trial_balance_flags_an_unbalanced_dv_journal(): void
    {
        [$school] = $this->tenant('FR6');
        $this->dv($school, '0006', '2026-03-01', 8000, 7000);

        $this->assertFalse($this->service()->trialBalance([$school->id], null, ...$this->range())['balanced']);
    }

    public function test_general_ledger_running_balance(): void
    {
        [$school] = $this->books('FR7');

        $ledger = $this->service()->ledger(self::CASH, [$school->id], null, ...$this->range());

        $this->assertSame('10000.00', $ledger['opening']);
        $this->assertSame(['60000.00', '52000.00'], $ledger['rows']->pluck('balance')->all());
        $this->assertSame('52000.00', $ledger['closing']);
        $this->assertSame(['2026-02-01', '2026-03-01'], $ledger['rows']->pluck('date')->all());
    }

    public function test_ledger_of_account_without_postings_is_zero(): void
    {
        [$school] = $this->books('FR8');

        $ledger = $this->service()->ledger('1999999999', [$school->id], null, ...$this->range());

        $this->assertSame(['0.00', '0.00', 0], [$ledger['opening'], $ledger['closing'], $ledger['rows']->count()]);
    }

    public function test_subsidiary_ledger_splits_by_breakdown_and_falls_back_to_payee(): void
    {
        [$school] = $this->books('FR9');
        $split = $this->dv($school, '0010', '2026-04-01', 5000, 5000);
        foreach ([[1, '5020301000', 'Travelling Expenses', 3000], [2, '5020399000', 'Other Supplies Expenses', 2000]] as [$no, $code, $title, $amount]) {
            DvBreakdownLine::create(['organization_id' => $school->organization_id, 'liquidation_report_id' => $split->id, 'journal_line_no' => 2, 'line_no' => $no, 'account_code' => $code, 'account_title' => $title, 'debit' => 0, 'credit' => $amount]);
        }

        $subs = $this->service()->subsidiary(self::CASH, [$school->id], null, ...$this->range())->keyBy('sub');

        $this->assertSame('8000.00', $subs['Payee 0001']['credit']);
        $this->assertSame('3000.00', $subs['Travelling Expenses']['credit']);
        $this->assertSame('2000.00', $subs['Other Supplies Expenses']['credit']);
        $this->assertSame('10000.00', $subs['Balance brought forward']['debit']);
        $this->assertFalse($subs->has('Payee 0010'));
    }

    public function test_reports_are_empty_but_valid_with_no_postings(): void
    {
        [$school] = $this->tenant('FR10');

        $tb = $this->service()->trialBalance([$school->id], null, ...$this->range());

        $this->assertCount(0, $tb['rows']);
        $this->assertTrue($tb['balanced']);
        $this->assertCount(0, $this->service()->subsidiary(self::CASH, [$school->id], null, ...$this->range()));
    }

    public function test_income_statement_shows_revenue_expenses_and_surplus(): void
    {
        [$school] = $this->books('FR11');

        $is = $this->service()->incomeStatement([$school->id], null, ...$this->range());

        $this->assertSame(['50000.00', '8000.00', '42000.00'], [$is['totalRevenue'], $is['totalExpenses'], $is['surplus']]);
        $this->assertSame('8000.00', $is['expenses']->flatten(1)->first()['amount']);
    }

    public function test_cash_flow_direct_method_ties_to_the_cash_account(): void
    {
        [$school] = $this->books('FR5B');

        $flow = $this->service()->cashFlow([$school->id], null, ...$this->range());

        $this->assertSame('50000.00', $flow['totals']['Cash received']);
        $this->assertSame('-8000.00', $flow['totals']['Cash paid']);
        $this->assertSame(['10000.00', '42000.00', '52000.00'], [$flow['beginning'], $flow['net'], $flow['ending']]);
        $this->assertTrue($flow['balanced']);
    }

    public function test_cash_flow_page_shows_for_an_accounting_user(): void
    {
        [, $user] = $this->books('FR5C');

        $this->actingAs($user)->get(route('financial-reports', ['report' => 'cf', 'from' => '2026-02-01', 'to' => '2026-12-31']))
            ->assertOk()->assertSee('Cash at the end')->assertSee('52,000.00');
    }

    public function test_balance_sheet_balances(): void
    {
        [$school] = $this->books('FR12');

        $bs = $this->service()->balanceSheet([$school->id], null, Carbon::parse('2026-12-31'));

        $this->assertSame(['52000.00', '0.00', '52000.00'], [$bs['totalAssets'], $bs['totalLiabilities'], $bs['totalEquity']]);
        $this->assertTrue($bs['balanced']);
        $this->assertSame('42000.00', $bs['equity']->firstWhere('account_title', 'Current surplus/(deficit)')['amount']);
    }

    public function test_balance_sheet_as_of_before_the_dv_excludes_it(): void
    {
        [$school] = $this->books('FR13');

        $this->assertSame('60000.00', $this->service()->balanceSheet([$school->id], null, Carbon::parse('2026-02-15'))['totalAssets']);
    }

    public function test_statements_with_no_postings_are_zero(): void
    {
        [$school] = $this->tenant('FR14');

        $is = $this->service()->incomeStatement([$school->id], null, ...$this->range());
        $bs = $this->service()->balanceSheet([$school->id], null, Carbon::parse('2026-12-31'));

        $this->assertSame(['0.00', '0.00'], [$is['surplus'], $bs['totalAssets']]);
        $this->assertTrue($bs['balanced']);
    }

    private function page(User $user, School $school, array $query = [])
    {
        return $this->actingAs($user)->get(route('financial-reports', $query + ['school_id' => $school->id, 'from' => '2026-02-01', 'to' => '2026-12-31']));
    }

    public function test_each_report_tab_renders_with_the_fixture_numbers(): void
    {
        [$school, $user] = $this->books('FR15');

        $this->page($user, $school, ['report' => 'tb'])->assertOk()->assertSee('52,000.00')->assertSee('60,000.00')->assertDontSee('Out of balance');
        $this->page($user, $school, ['report' => 'gl', 'account' => self::CASH])->assertOk()->assertSee('NCA-1')->assertSee('52,000.00');
        $this->page($user, $school, ['report' => 'sl', 'account' => self::CASH])->assertOk()->assertSee('Payee 0001')->assertSee('MOOE');
        $this->page($user, $school, ['report' => 'is'])->assertOk()->assertSee('42,000.00');
        $this->page($user, $school, ['report' => 'bs'])->assertOk()->assertSee('52,000.00')->assertSee('Current surplus/(deficit)');
    }

    public function test_trial_balance_page_warns_when_out_of_balance(): void
    {
        [$school, $user] = $this->tenant('FR16');
        $this->dv($school, '0016', '2026-03-01', 8000, 7000);

        $this->page($user, $school)->assertOk()->assertSee('Out of balance');
    }

    public function test_from_after_to_is_rejected(): void
    {
        [$school, $user] = $this->tenant('FR17');

        $this->page($user, $school, ['from' => '2026-12-31', 'to' => '2026-01-01'])->assertSessionHasErrors('to');
    }

    public function test_another_schools_data_is_forbidden(): void
    {
        [$school, $user] = $this->tenant('FR18');
        [$other] = $this->tenant('FR19');

        $this->page($user, $other)->assertForbidden();
    }

    public function test_page_renders_with_no_data_and_needs_accounting_permission(): void
    {
        [$school, $user] = $this->tenant('FR20');
        [$viewerSchool, $viewer] = $this->tenant('FR21', 'viewer');

        foreach (['tb', 'gl', 'sl', 'is', 'bs'] as $report) {
            $this->page($user, $school, ['report' => $report])->assertOk();
        }
        $this->page($viewer, $viewerSchool, [])->assertForbidden();
    }

    public function test_opening_balance_can_be_added_and_deleted_only_with_accounting_manage(): void
    {
        [$school, $user] = $this->tenant('FR22');
        $auditor = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'auditor']);
        ChartOfAccount::create(['organization_id' => $school->organization_id, 'code' => self::CASH, 'title' => 'Cash in Bank', 'category' => 'Assets']);
        $form = ['school_id' => $school->id, 'as_of' => '2026-01-01', 'account_code' => self::CASH, 'debit' => 500];

        $this->actingAs($user)->post(route('financial-reports.opening.store'), $form)->assertSessionHas('success');
        $opening = OpeningBalance::firstOrFail();
        $this->assertSame('500.00', (string) $opening->debit);
        $this->assertSame('General Fund', $opening->fund_cluster);

        $this->actingAs($user)->post(route('financial-reports.opening.store'), $form)->assertSessionHasErrors('account_code');
        $this->assertSame(1, OpeningBalance::count());

        $this->actingAs($auditor)->post(route('financial-reports.opening.store'), $form)->assertForbidden();
        $this->actingAs($auditor)->delete(route('financial-reports.opening.destroy', $opening))->assertForbidden();

        $this->actingAs($user)->delete(route('financial-reports.opening.destroy', $opening))->assertSessionHas('success');
        $this->assertSame(0, OpeningBalance::count());
    }
}
