<?php

namespace Tests\Feature;

use App\Models\CashFundReceipt;
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
}
