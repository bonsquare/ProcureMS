<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualDvTest extends TestCase
{
    use RefreshDatabase;

    private function setup_(): array
    {
        $organization = Organization::create(['name' => 'md', 'slug' => 'md', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'MD', 'name' => 'Manual School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        return [$school, $master];
    }

    public function test_a_dv_can_be_started_without_an_ors_and_opens_in_ready_for_dv(): void
    {
        [$school, $master] = $this->setup_();

        $this->actingAs($master)->post(route('accounting.dv.manual'), ['school_id' => $school->id, 'payee' => 'Walk-in Payee', 'purpose' => 'Reimbursement', 'amount' => 1250.5])
            ->assertSessionHasNoErrors();

        $report = LiquidationReport::firstOrFail();
        $this->assertNull($report->ors_number);
        $this->assertSame('approved', $report->status);
        $this->assertNotEmpty($report->report_number);
        $this->assertNull($report->dv_number);

        $this->get(route('accounting', ['tab' => 'for_dv', 'open_dv' => $report->id]))->assertOk()
            ->assertSee($report->report_number)->assertSee('data-report-id="'.$report->id.'"', false)->assertSee('New DV (no ORS)');
    }

    public function test_the_dv_of_a_record_without_an_ors_is_completed_goes_to_cash_and_prints(): void
    {
        [$school, $master] = $this->setup_();
        $this->actingAs($master)->post(route('accounting.dv.manual'), ['school_id' => $school->id, 'payee' => 'Walk-in Payee', 'purpose' => 'Reimbursement', 'amount' => 1000]);
        $report = LiquidationReport::firstOrFail();
        $expense = collect(ChartOfAccount::standardAccounts())->firstWhere('category', 'MOOE')['code'];

        $this->post(route('accounting.dv.store', $report), [
            'dv_date' => now()->toDateString(), 'payee' => 'Walk-in Payee', 'dv_particulars' => 'Reimbursement', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $expense, 'debit' => '1000', 'credit' => ''], ['account_code' => '1010404000', 'debit' => '', 'credit' => '1000']],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($report->fresh()->dv_number);
        $this->get(route('cash'))->assertOk()->assertSee('Walk-in Payee');
        $this->get(route('accounting.dv.print', $report))->assertOk();
    }

    public function test_a_record_without_an_ors_is_not_an_obligation_or_an_ors_in_the_lists(): void
    {
        [$school, $master] = $this->setup_();
        $this->actingAs($master)->post(route('accounting.dv.manual'), ['school_id' => $school->id, 'payee' => 'Walk-in Payee', 'purpose' => 'Reimbursement', 'amount' => 1000]);
        $report = LiquidationReport::firstOrFail();

        $this->assertSame(0, LiquidationReport::withOrs()->count());
        $this->get(route('accounting'))->assertOk(); // the page after the redirect shows the one-time message
        $this->get(route('budget'))->assertOk()->assertDontSee($report->report_number);
        $this->get(route('liquidation'))->assertOk()->assertDontSee($report->report_number);
    }

    public function test_it_needs_the_accounting_permission_and_a_school_the_user_may_use(): void
    {
        [$school] = $this->setup_();
        $other = Organization::create(['name' => 'other', 'slug' => 'other', 'status' => 'active']);
        $otherSchool = School::create(['organization_id' => $other->id, 'code' => 'OT', 'name' => 'Other School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $other->id, 'school_id' => $otherSchool->id, 'role' => 'viewer']);

        $this->actingAs($user)->post(route('accounting.dv.manual'), ['school_id' => $school->id, 'payee' => 'X', 'purpose' => 'Y', 'amount' => 10])->assertForbidden();
        $this->assertSame(0, LiquidationReport::count());
    }
}
