<?php

namespace Tests\Feature;

use App\Models\AppCsePlan;
use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\CashRegisterCheck;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\MasterTransaction;
use App\Models\PowItem;
use App\Models\PowPlan;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/**
 * T-004: a user of organization A can neither list nor open the money records of organization B
 * (budget, ORS/DV, journal, cancelled checks, fund receipts, SOB, Program of Works, APP-CSE, master transactions).
 */
class MoneyModelIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{a: array<string, mixed>, b: array<string, mixed>, records: array<string, mixed>} */
    private function twoOrganizations(): array
    {
        $a = SobFixture::make(['slug' => 'isoa']);
        $b = SobFixture::make(['slug' => 'isob']);
        $org = $b['organization']->id;
        $school = $b['school']->id;

        $line = BudgetAllocation::create([
            'organization_id' => $org, 'school_id' => $school, 'office' => 'B School', 'fiscal_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'source_of_fund' => 'MOOE', 'program' => 'Program B', 'particulars' => 'Line of B', 'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0,
            'budget_ref_no' => 'BA-B-0001', 'created_by' => $b['user']->id,
        ]);
        $report = LiquidationReport::create([
            'organization_id' => $org, 'school_id' => $school, 'report_number' => 'LR-B-1', 'ors_number' => 'ORS-B-1', 'amount' => 5000, 'status' => 'approved',
            'payee' => 'Payee of B', 'purpose' => 'Purpose of B', 'dv_number' => 'DV-B-1', 'dv_date' => '2026-07-01', 'payment_mode' => 'MDS Check',
            'payment_reference' => 'CHK-B', 'paid_at' => '2026-07-02', 'budget_allocation_id' => $line->id,
        ]);
        $journal = DvJournalLine::create(['organization_id' => $org, 'liquidation_report_id' => $report->id, 'line_no' => 1, 'account_code' => '5-02-03-010', 'account_title' => 'Account', 'debit' => 5000, 'credit' => 0]);
        $cancelled = CancelledCheck::create([
            'organization_id' => $org, 'school_id' => $school, 'liquidation_report_id' => $report->id, 'ors_number' => 'ORS-B-1', 'dv_number' => 'DV-B-1', 'check_number' => 'OLD-B',
            'payment_mode' => 'MDS Check', 'paid_at' => '2026-07-02', 'amount' => 5000, 'reason' => 'Test', 'cancelled_by' => $b['user']->id, 'cancelled_at' => now(),
        ]);
        $receipt = CashFundReceipt::create(['organization_id' => $org, 'school_id' => $school, 'received_at' => '2026-07-01', 'kind' => 'nca', 'fund_cluster' => 'General Fund', 'amount' => 9000, 'created_by' => $b['user']->id]);
        $sob = app(SobService::class)->create($b['school'], 2026, 1, 'MOOE', $b['user']);
        $pow = PowPlan::create(['organization_id' => $org, 'school_id' => $school, 'sob_plan_id' => $sob->id, 'program' => 'Program B', 'project_title' => 'Project B', 'labor_cost' => 0, 'created_by' => $b['user']->id]);
        $powItem = PowItem::create(['organization_id' => $org, 'pow_plan_id' => $pow->id, 'description' => 'Item of B', 'unit' => 'pcs', 'quantity' => 1, 'unit_cost' => 10, 'amount' => 10, 'sort' => 1]);
        $appCse = AppCsePlan::withoutGlobalScopes()->create(['organization_id' => $org, 'school_id' => $school, 'fiscal_year' => 2026, 'catalog_year' => 2026, 'status' => 'draft', 'created_by' => $b['user']->id]);

        return ['a' => $a, 'b' => $b, 'records' => compact('line', 'report', 'journal', 'cancelled', 'receipt', 'sob', 'pow', 'powItem', 'appCse')];
    }

    public function test_control_the_owning_organization_can_open_the_same_records(): void
    {
        ['b' => $b, 'records' => $r] = $this->twoOrganizations();
        $this->actingAs($b['user']);

        $this->get(route('liquidation.print', $r['report']))->assertOk();
        $this->get(route('budget.allocation.show', $r['line']))->assertOk();
        $this->get(route('planning.pow.show', $r['pow']))->assertOk();
        $this->get(route('planning.pow.print', $r['pow']))->assertOk();
        $this->get(route('planning.sob.show', $r['sob']))->assertOk();
        $this->get(route('planning.app-cse.show', $r['appCse']))->assertOk();
        CashRegisterCheck::create(['organization_id' => $b['school']->organization_id, 'school_id' => $b['school']->id, 'source_of_fund' => '', 'quarter_start' => '2026-07-01', 'check_number' => 'CHK-B']);
        $this->get(route('cash.report.register', ['school_id' => $b['school']->id, 'from' => '2026-07-01', 'to' => '2026-09-30']))->assertOk()->assertSee('Payee of B');
    }

    public function test_a_user_of_one_organization_lists_none_of_the_money_records_of_another(): void
    {
        ['a' => $a, 'records' => $records] = $this->twoOrganizations();
        $this->assertGreaterThan(0, MasterTransaction::withoutGlobalScopes()->where('organization_id', $records['sob']->organization_id)->count());

        $this->actingAs($a['user']);

        foreach ([BudgetAllocation::class, LiquidationReport::class, DvJournalLine::class, CancelledCheck::class, CashFundReceipt::class, SobPlan::class, PowPlan::class, PowItem::class, AppCsePlan::class] as $model) {
            $this->assertSame(0, $model::count(), $model.' leaks records of another organization.');
        }
        $this->assertSame(0, MasterTransaction::where('organization_id', $records['sob']->organization_id)->count());
        $this->assertNull(LiquidationReport::find($records['report']->id));
    }

    public function test_a_user_of_one_organization_cannot_open_or_change_the_records_of_another_by_url(): void
    {
        ['a' => $a, 'b' => $b, 'records' => $r] = $this->twoOrganizations();
        $this->actingAs($a['user']);

        $this->get(route('liquidation.print', $r['report']))->assertNotFound();
        $this->patch(route('liquidation.status', $r['report']), ['status' => 'approved'])->assertNotFound();
        $this->post(route('cash.cancel', $r['report']), [])->assertNotFound();
        $this->post(route('cash.pay', $r['report']), [])->assertNotFound();
        $this->get(route('budget.allocation.show', $r['line']))->assertNotFound();
        $this->put(route('budget.allocation.update', $r['line']), ['amount' => 1])->assertNotFound();
        $this->delete(route('budget.allocation.destroy', $r['line']))->assertNotFound();
        $this->get(route('planning.pow.show', $r['pow']))->assertNotFound();
        $this->get(route('planning.pow.print', $r['pow']))->assertNotFound();
        $this->delete(route('planning.pow.destroy', $r['pow']))->assertNotFound();
        $this->get(route('planning.app-cse.show', $r['appCse']))->assertNotFound();
        $this->get(route('planning.app-cse.print', $r['appCse']))->assertNotFound();
        $this->get(route('planning.sob.show', $r['sob']))->assertNotFound();
        $this->get(route('planning.sob.print', $r['sob']))->assertNotFound();
        $this->put(route('cash.fund.update', $r['receipt']), ['amount' => 1])->assertNotFound();
        $this->delete(route('cash.fund.destroy', $r['receipt']))->assertNotFound();

        $this->assertSame('5000.00', $r['line']->fresh()->amount);
        $this->assertSame('approved', LiquidationReport::withoutGlobalScopes()->find($r['report']->id)->status);
        $this->assertNotNull(PowPlan::withoutGlobalScopes()->find($r['pow']->id));
        $this->assertNotNull(CashFundReceipt::withoutGlobalScopes()->find($r['receipt']->id));
        $this->assertNotNull(BudgetAllocation::withoutGlobalScopes()->find($r['line']->id));
    }

    public function test_the_cash_reports_of_another_organizations_school_are_refused_and_show_none_of_its_figures(): void
    {
        ['a' => $a, 'b' => $b] = $this->twoOrganizations();
        $this->actingAs($a['user']);

        foreach (['cash.report.roci', 'cash.report.checks', 'cash.report.register', 'cash.report.bank'] as $name) {
            $response = $this->get(route($name, ['school_id' => $b['school']->id, 'from' => '2026-07-01', 'to' => '2026-09-30']));
            $this->assertNotSame(200, $response->getStatusCode(), $name.' opened for another organization.');
            $this->assertStringNotContainsString('Payee of B', (string) $response->getContent());
        }
    }
}
