<?php

namespace Tests\Feature;

use App\Models\CancelledCheck;
use App\Models\CashRegisterCheck;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterChecksTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(string $code): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin'])];
    }

    private function paid(School $school, string $check): LiquidationReport
    {
        return LiquidationReport::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-'.$check, 'ors_number' => 'ORS-'.$check, 'dv_number' => 'DV-'.$check,
            'dv_date' => '2026-08-05', 'amount' => 1000, 'status' => 'approved', 'payee' => 'Payee '.$check, 'purpose' => 'Purpose',
            'payment_mode' => 'MDS Check', 'payment_reference' => $check, 'paid_at' => '2026-08-05',
        ]);
    }

    private function scope(School $school, array $extra = []): array
    {
        return ['school_id' => $school->id, 'source_of_fund' => '', 'from' => '2026-07-01'] + $extra;
    }

    public function test_the_register_prints_only_the_typed_checks_in_series_and_a_cancelled_one_is_marked(): void
    {
        [$school, $user] = $this->tenant('RC1');
        $paid = $this->paid($school, '0002');
        $this->paid($school, '0003');
        $this->paid($school, '0001');
        CancelledCheck::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'liquidation_report_id' => $paid->id, 'ors_number' => 'ORS-X', 'dv_number' => 'DV-X', 'check_number' => '0004', 'payment_mode' => 'MDS Check', 'paid_at' => '2026-08-06', 'amount' => 300, 'reason' => 'Wrong', 'cancelled_by' => $user->id, 'cancelled_at' => now()]);

        $this->actingAs($user)->post(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '0001']))->assertOk();
        $this->actingAs($user)->post(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '0004']))->assertOk();
        $this->actingAs($user)->post(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '0002']))->assertOk();

        $html = $this->actingAs($user)->get(route('cash.report.register', ['school_id' => $school->id, 'from' => '2026-07-01', 'to' => '2026-09-30']))->assertOk()->assertSee('Payee 0001')->assertSee('Payee 0002')->assertSee('Cancelled Check')->assertDontSee('Payee 0003')->getContent();
        $this->assertLessThan(strpos($html, 'Payee 0002'), strpos($html, 'Payee 0001'));
    }

    public function test_the_register_of_each_fund_shows_the_whole_series_with_the_amount_only_on_its_own_checks(): void
    {
        [$school, $user] = $this->tenant('RC5');
        $this->paid($school, '0001')->update(['source_of_fund' => 'MOOE', 'amount' => 1111]);
        $this->paid($school, '0002')->update(['source_of_fund' => 'ARAL', 'amount' => 2222]);
        foreach (['0001', '0002'] as $number) {
            $this->actingAs($user)->post(route('cash.report.register.checks.store'), $this->scope($school, ['source_of_fund' => 'MOOE', 'check_number' => $number]))->assertOk();
        }

        foreach ([['MOOE', '1,111.00', '2,222.00'], ['ARAL', '2,222.00', '1,111.00']] as [$fund, $shown, $hidden]) {
            $this->actingAs($user)->get(route('cash.report.register', ['school_id' => $school->id, 'from' => '2026-07-01', 'to' => '2026-09-30', 'source_of_fund' => $fund]))
                ->assertOk()->assertSee('Payee 0001')->assertSee('Payee 0002')->assertSee($shown)->assertDontSee($hidden);
        }
    }

    public function test_a_number_that_is_neither_paid_nor_cancelled_is_refused_and_a_typed_check_can_be_removed(): void
    {
        [$school, $user] = $this->tenant('RC2');
        $this->paid($school, '0001');

        $this->actingAs($user)->postJson(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '9999']))->assertStatus(422)->assertJsonValidationErrors('check_number');
        $this->actingAs($user)->postJson(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '0001']))->assertOk();
        $this->actingAs($user)->getJson(route('cash.report.register.checks', $this->scope($school)))->assertOk()->assertJsonFragment(['check_number' => '0001', 'status' => 'Paid', 'payee' => 'Payee 0001']);

        $this->actingAs($user)->deleteJson(route('cash.report.register.checks.destroy', CashRegisterCheck::firstOrFail()))->assertOk();
        $this->assertSame(0, CashRegisterCheck::count());
    }

    public function test_another_organizations_school_and_checks_are_refused(): void
    {
        [$school, $user] = $this->tenant('RC3');
        [$other] = $this->tenant('RC4');
        $this->paid($other, '0001');
        $foreign = CashRegisterCheck::create(['organization_id' => $other->organization_id, 'school_id' => $other->id, 'source_of_fund' => '', 'quarter_start' => '2026-07-01', 'check_number' => '0001']);

        $this->actingAs($user)->postJson(route('cash.report.register.checks.store'), $this->scope($other, ['check_number' => '0001']))->assertForbidden();
        $this->actingAs($user)->getJson(route('cash.report.register.checks', $this->scope($other)))->assertForbidden();
        $this->actingAs($user)->deleteJson(route('cash.report.register.checks.destroy', $foreign))->assertNotFound();
        $this->actingAs($user)->postJson(route('cash.report.register.checks.store'), $this->scope($school, ['check_number' => '0001']))->assertStatus(422);
    }
}
