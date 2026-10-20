<?php

namespace Tests\Support;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\School;
use App\Models\SobItem;
use App\Models\SobPlan;

/** A budget line (fund, account, 2026) that an approved SOB stands behind: an ORS may only be charged to such a line. */
trait SobBackedBudgetLine
{
    protected function sobBackedLine(School $school, string $fund = 'MOOE', float $amount = 1000000): BudgetAllocation
    {
        return $this->backWithSob(BudgetAllocation::withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $school->id, 'fiscal_year' => 2026, 'source_of_fund' => $fund, 'program' => 'Program'],
            ['organization_id' => $school->organization_id, 'particulars' => 'Supplies', 'uacs_code' => '5020399000', 'amount' => $amount, 'q1_amount' => $amount, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0],
        ));
    }

    /** Puts an approved SOB behind an existing budget line: the same fund, year, account (its UACS code) and program. */
    protected function backWithSob(BudgetAllocation $line): BudgetAllocation
    {
        $school = School::withoutGlobalScopes()->findOrFail($line->school_id);
        $aip = Aip::withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'fiscal_year' => $line->fiscal_year], ['organization_id' => $school->organization_id, 'entity' => 'school', 'status' => 'approved']);
        $kra = AipKra::withoutGlobalScopes()->firstOrCreate(['aip_id' => $aip->id, 'program' => $line->program ?: 'Program'], ['organization_id' => $school->organization_id, 'pillar' => 'Access', 'kra' => 'KRA']);
        $activity = AipActivity::withoutGlobalScopes()->firstOrCreate(['aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Activity'], ['organization_id' => $school->organization_id, 'physical_target' => 1]);
        $account = ChartOfAccount::firstOrCreate(['organization_id' => $school->organization_id, 'code' => $line->uacs_code ?: '5020399000'], ['title' => 'Supplies Expenses', 'category' => 'Expense']);
        $line->update(['chart_of_account_id' => $account->id, 'aip_id' => $aip->id]);
        $plan = SobPlan::withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'fiscal_year' => $line->fiscal_year, 'quarter' => 1, 'fund_source' => $line->source_of_fund], ['organization_id' => $school->organization_id, 'aip_id' => $aip->id, 'status' => 'approved']);
        SobItem::withoutGlobalScopes()->firstOrCreate(['sob_plan_id' => $plan->id, 'chart_of_account_id' => $account->id, 'aip_activity_id' => $activity->id], ['organization_id' => $school->organization_id, 'particulars' => 'Supplies', 'quantity' => 1, 'unit' => 'lot', 'unit_cost' => 1, 'amount' => 1]);

        return $line->fresh();
    }
}
