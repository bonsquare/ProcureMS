<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobBackedBudgetLine;
use Tests\TestCase;

/** The budget lines an ORS is charged to can be edited (added, removed, re-amounted) until the ORS has a DV or a payment. */
class OrsEditChargesTest extends TestCase
{
    use RefreshDatabase;
    use SobBackedBudgetLine;

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'edit', 'slug' => 'edit', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'EDT', 'name' => 'Edit School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$school, $user];
    }

    private function line(School $school, User $user, string $code, string $fund = 'MOOE', float $amount = 100000): BudgetAllocation
    {
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'program' => 'Program', 'source_of_fund' => $fund, 'uacs_code' => $code,
            'particulars' => 'Line '.$code, 'amount' => $amount, 'q1_amount' => $amount, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'created_by' => $user->id, 'budget_ref_no' => 'BA-'.$code,
        ]);

        return $this->backWithSob($line);
    }

    /** @return array{0: LiquidationReport, 1: User, 2: School, 3: array<int, BudgetAllocation>} an ORS charged ₱3,000 to one line and ₱1,500 to another. */
    private function splitOrs(): array
    {
        [$school, $user] = $this->tenant();
        $lines = [$this->line($school, $user, '5020402000'), $this->line($school, $user, '5020401000'), $this->line($school, $user, '5020301000')];
        $this->actingAs($user)->post(route('liquidation.store'), [
            'school_id' => $school->id, 'source_of_fund' => 'MOOE', 'purpose' => 'Electricity and water', 'payee' => 'Utility Co', 'amount' => '4500',
            'charges' => [['budget_allocation_id' => $lines[0]->id, 'amount' => '3000'], ['budget_allocation_id' => $lines[1]->id, 'amount' => '1500']],
        ])->assertSessionHasNoErrors();

        return [LiquidationReport::firstOrFail(), $user, $school, $lines];
    }

    private function form(LiquidationReport $ors, array $charges, array $extra = []): array
    {
        return ['payee' => 'Utility Co', 'purpose' => 'Electricity and water', 'amount' => (string) $ors->amount, 'reason' => 'Corrected lines', 'pin' => '123456', 'charges' => $charges] + $extra;
    }

    public function test_the_amounts_can_change_and_a_line_can_be_added(): void
    {
        [$ors, $user, , $lines] = $this->splitOrs();

        $this->patch(route('liquidation.update', $ors), $this->form($ors, [
            ['budget_allocation_id' => $lines[0]->id, 'amount' => '2000'], ['budget_allocation_id' => $lines[1]->id, 'amount' => '1000'], ['budget_allocation_id' => $lines[2]->id, 'amount' => '500.50'],
        ]))->assertSessionHasNoErrors();

        $ors->refresh();
        $this->assertSame('3500.50', $ors->amount, 'a direct ORS takes the total of its lines');
        $this->assertSame([[$lines[0]->id, '2000.00'], [$lines[1]->id, '1000.00'], [$lines[2]->id, '500.50']], $ors->lines->map(fn ($part) => [$part->budget_allocation_id, $part->amount])->all());
        $this->assertSame('5020402000 ₱2,000.00 | 5020401000 ₱1,000.00 | 5020301000 ₱500.50', AuditLog::where('action', 'ors_resubmitted')->latest('id')->first()->metadata['after']['charges']);
    }

    public function test_a_split_can_go_back_to_one_line_and_the_old_parts_are_removed(): void
    {
        [$ors, , , $lines] = $this->splitOrs();

        $this->actingAs($ors->submitter ?? User::first())->patch(route('liquidation.update', $ors), $this->form($ors, [['budget_allocation_id' => $lines[2]->id, 'amount' => '4500']]))->assertSessionHasNoErrors();

        $ors->refresh();
        $this->assertCount(0, $ors->lines);
        $this->assertSame($lines[2]->id, $ors->budget_allocation_id);
        $this->assertSame('4500.00', $ors->amount);
    }

    public function test_a_line_without_enough_balance_or_of_another_fund_or_repeated_is_refused_and_nothing_changes(): void
    {
        [$ors, $user, $school, $lines] = $this->splitOrs();
        $small = $this->line($school, $user, '5029999000', 'MOOE', 100);
        $other = $this->line($school, $user, '5020501000', 'FHSE');

        $this->actingAs($user)->patch(route('liquidation.update', $ors), $this->form($ors, [['budget_allocation_id' => $small->id, 'amount' => '4500']]))->assertSessionHasErrors('charges');
        $this->patch(route('liquidation.update', $ors), $this->form($ors, [['budget_allocation_id' => $other->id, 'amount' => '4500']]))->assertSessionHasErrors('charges');
        $this->patch(route('liquidation.update', $ors), $this->form($ors, [['budget_allocation_id' => $lines[0]->id, 'amount' => '2000'], ['budget_allocation_id' => $lines[0]->id, 'amount' => '2500']]))->assertSessionHasErrors('charges');
        $this->patch(route('liquidation.update', $ors), array_diff_key($this->form($ors, []), ['charges' => 1]))->assertSessionHasNoErrors();
        $this->patch(route('liquidation.update', $ors), $this->form($ors, []))->assertSessionHasErrors('charges');

        $ors->refresh();
        $this->assertSame('4500.00', $ors->amount);
        $this->assertSame([$lines[0]->id, $lines[1]->id], $ors->lines->pluck('budget_allocation_id')->all());
    }

    public function test_an_ors_with_a_dv_keeps_its_lines_locked(): void
    {
        [$ors, $user, , $lines] = $this->splitOrs();
        $ors->update(['status' => 'approved', 'dv_number' => 'DV-1']);

        $this->actingAs($user)->patch(route('liquidation.update', $ors), $this->form($ors, [['budget_allocation_id' => $lines[2]->id, 'amount' => '4500']], ['payee' => $ors->payee]))->assertSessionHasNoErrors();

        $this->assertSame([$lines[0]->id, $lines[1]->id], $ors->fresh()->lines->pluck('budget_allocation_id')->all());
        $this->get(route('budget'))->assertOk()->assertSee('These lines are locked');
    }

    public function test_the_budget_page_shows_the_charge_editor_with_the_fund_and_the_current_lines(): void
    {
        [$ors, $user] = $this->splitOrs();

        $this->actingAs($user)->get(route('budget'))->assertOk()->assertSee('data-charge-editor', false)->assertSee('Charge to budget lines')->assertSee('Fund: MOOE')->assertSee('Add a budget line');
    }
}
