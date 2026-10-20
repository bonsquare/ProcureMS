<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobBackedBudgetLine;
use Tests\TestCase;

/** One ORS, one particulars, charged to several budget lines (UACS codes) each with its own amount. */
class OrsSplitChargesTest extends TestCase
{
    use RefreshDatabase;
    use SobBackedBudgetLine;

    /** @return array{0: School, 1: User} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'split', 'slug' => 'split', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SPL', 'name' => 'Split School', 'status' => 'active']);
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

    private function payload(School $school, array $charges, ?float $amount = null): array
    {
        return ['school_id' => $school->id, 'source_of_fund' => 'MOOE', 'purpose' => 'Electricity and water', 'payee' => 'Utility Co', 'amount' => (string) ($amount ?? collect($charges)->sum('amount')), 'charges' => $charges];
    }

    public function test_an_ors_is_charged_to_several_uacs_codes_each_with_its_own_amount(): void
    {
        [$school, $user] = $this->tenant();
        $electric = $this->line($school, $user, '5020402000');
        $water = $this->line($school, $user, '5020401000');
        $this->actingAs($user);

        $this->post(route('liquidation.store'), $this->payload($school, [
            ['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $water->id, 'amount' => '1500.50'],
        ]))->assertSessionHasNoErrors();

        $ors = LiquidationReport::firstOrFail();
        $this->assertSame('4500.50', $ors->amount);
        $this->assertSame([[$electric->id, '3000.00'], [$water->id, '1500.50']], $ors->lines->map(fn ($part) => [$part->budget_allocation_id, $part->amount])->all());
        $this->assertSame($electric->id, $ors->budget_allocation_id);

        $matrix = app(BudgetService::class)->matrix(collect([$electric->fresh(), $water->fresh()]));
        $this->assertSame([3000.0, 1500.5], [$matrix[0]['obligated'], $matrix[1]['obligated']], 'each line carries only its own part');

        $this->get(route('liquidation.print', $ors))->assertOk()->assertSee('5020402000')->assertSee('5020401000')->assertSee('3,000.00')->assertSee('1,500.50')->assertSee('4,500.50');
    }

    public function test_the_parts_must_add_up_and_each_line_must_be_of_one_fund_and_in_an_approved_sob(): void
    {
        [$school, $user] = $this->tenant();
        $electric = $this->line($school, $user, '5020402000');
        $water = $this->line($school, $user, '5020401000');
        $aral = $this->line($school, $user, '5020301000', 'ARAL');
        $manual = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'program' => 'By hand', 'source_of_fund' => 'MOOE', 'uacs_code' => '5029999000',
            'particulars' => 'No SOB', 'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-NOSOB',
        ]);
        $this->actingAs($user);

        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $water->id, 'amount' => '1000']], 5000))->assertSessionHasErrors('charges');
        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $aral->id, 'amount' => '1000']]))->assertSessionHasErrors('charges');
        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $manual->id, 'amount' => '1000']]))->assertSessionHasErrors('budget_allocation_id');
        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $electric->id, 'amount' => '1000']]))->assertSessionHasErrors('charges');
        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_a_part_cannot_exceed_what_its_line_has_left(): void
    {
        [$school, $user] = $this->tenant();
        $electric = $this->line($school, $user, '5020402000', amount: 2000);
        $water = $this->line($school, $user, '5020401000');
        $this->actingAs($user);

        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $water->id, 'amount' => '1000']]))->assertSessionHasErrors('charges');
        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_the_cash_register_divides_the_payment_among_the_uacs_columns(): void
    {
        [$school, $user] = $this->tenant();
        $electric = $this->line($school, $user, '5020402000');
        $water = $this->line($school, $user, '5020401000');
        $this->actingAs($user);
        $this->post(route('liquidation.store'), $this->payload($school, [['budget_allocation_id' => $electric->id, 'amount' => '3000'], ['budget_allocation_id' => $water->id, 'amount' => '1500']]))->assertSessionHasNoErrors();
        LiquidationReport::firstOrFail()->update(['status' => 'approved', 'dv_number' => 'DV-1', 'paid_at' => '2026-08-05', 'payment_reference' => '0001', 'payment_mode' => 'MDS Check']);

        $report = app(CashReportService::class)->build($school, Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30'));

        $this->assertSame([3000.0, 1500.0], collect($report['lines']->first()['splits'])->values()->all());
        $this->assertSame(4500.0, $report['lines']->first()['amount']);
    }
}
