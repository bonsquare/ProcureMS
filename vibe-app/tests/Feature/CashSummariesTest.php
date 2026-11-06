<?php

namespace Tests\Feature;

use App\Models\CashFundReceipt;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashSummariesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User, 2: LiquidationReport} the school, a master user and the DV that withholds the tax */
    private function lubas(): array
    {
        $organization = Organization::create(['name' => 'summ', 'slug' => 'summ', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SUM', 'name' => 'Summary School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $dv = function (string $number, string $date, float $amount, bool $nonCash, ?string $paid, array $journal, array $extra = []) use ($organization, $school) {
            $report = LiquidationReport::create([
                'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => null, 'ors_kind' => 'other',
                'amount' => $amount, 'status' => 'approved', 'payee' => 'Survive Marketing Corp', 'purpose' => 'Purchase', 'source_of_fund' => 'MOOE',
                'dv_number' => '2026-03-'.$number, 'dv_date' => $date, 'dv_non_cash' => $nonCash, 'paid_at' => $paid,
            ] + $extra);
            foreach ($journal as $line => [$account, $debit, $credit]) {
                DvJournalLine::create(['organization_id' => $organization->id, 'liquidation_report_id' => $report->id, 'line_no' => $line + 1, 'account_code' => $account, 'account_title' => $account, 'debit' => $debit, 'credit' => $credit]);
            }

            return $report;
        };
        $dv('0000', '2026-03-02', 181500, true, null, [['1010404000', 181500, 0], ['1990101000', 0, 181500]]);
        $purchase = $dv('0001', '2026-03-12', 89230, false, '2026-03-12', [['5020301000', 89230, 0], ['1010404000', 0, 84449.82], ['2020101000', 0, 4780.18]], ['dv_deductions' => ['withholding_tax' => 4780.18]]);
        $dv('0002', '2026-04-02', 4780.18, false, '2026-04-02', [['2020101000', 4780.18, 0], ['1010404000', 0, 4780.18]], ['remittance' => ['sources' => [(string) $purchase->id => 4780.18]]]);

        return [$school, $master, $purchase];
    }

    public function test_the_summaries_show_cash_position_tax_advances_and_paid_dvs_by_payee(): void
    {
        [$school, $master] = $this->lubas();

        $page = $this->actingAs($master)->get(route('cash.summaries', ['school_id' => $school->id, 'from' => '2026-01-01', 'to' => '2026-06-30']))->assertOk();

        $page->assertSeeInOrder(['Cash position', 'Beginning balance', '0.00', 'Cash received', '181,500.00', 'Cash paid out', '89,230.00', 'Ending balance', '92,270.00']);
        $page->assertSeeInOrder(['Tax withheld and remitted', '2026-03-0001', '4,780.18', '4,780.18', '0.00']);
        $page->assertSeeInOrder(['Received', '181,500.00', 'Liquidated', '0.00', 'Still owed', '181,500.00']);
        $page->assertSeeInOrder(['Paid DVs by payee', 'Survive Marketing Corp', '2', '94,010.18', '4,780.18', '89,230.00']);
    }

    public function test_tax_not_yet_remitted_as_of_the_period_end_is_shown_as_still_to_remit(): void
    {
        [$school, $master] = $this->lubas();

        $this->actingAs($master)->get(route('cash.summaries', ['school_id' => $school->id, 'from' => '2026-01-01', 'to' => '2026-03-31']))->assertOk()
            ->assertSeeInOrder(['2026-03-0001', '4,780.18', '0.00', '4,780.18']);
    }

    public function test_a_typed_cash_advance_names_the_dv_that_already_records_it(): void
    {
        [$school, $master] = $this->lubas();
        LiquidationReport::where('dv_non_cash', true)->update(['source_of_fund' => null]); // a DV made without a fund still names itself on the advance
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-03-02', 'kind' => 'cash_advance', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 181500, 'created_by' => $master->id]);
        CashFundReceipt::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'received_at' => '2026-03-03', 'kind' => 'cash_advance', 'source_of_fund' => 'MOOE', 'fund_cluster' => 'General Fund', 'amount' => 777, 'created_by' => $master->id]);

        $rows = $this->actingAs($master)->getJson(route('cash.fund.index', ['school_id' => $school->id, 'from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk()->json();

        $this->assertSame('DV 2026-03-0000', $rows[0]['recorded_by_dv']);
        $this->assertSame('', $rows[1]['recorded_by_dv']);
    }
}
