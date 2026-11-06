<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_accounting_cards_keep_a_non_cash_advance_out_of_the_obligations_and_count_paid_cash_net_of_withheld_tax(): void
    {
        $organization = Organization::create(['name' => 'cards', 'slug' => 'cards', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'CRD', 'name' => 'Cards School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $make = fn (string $number, float $amount, bool $nonCash, ?string $paid, array $deductions = []) => LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number,
            'ors_kind' => 'other', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase',
            'dv_number' => '2026-03-'.$number, 'dv_date' => '2026-03-12', 'dv_non_cash' => $nonCash, 'paid_at' => $paid, 'dv_deductions' => $deductions,
        ]);
        $make('0001', 1000, false, null);
        $make('0002', 2000, false, '2026-03-20', ['withholding_tax' => 300]);
        $make('0003', 50000, true, null);
        $make('0004', 700, false, '2026-03-21')->update(['ors_number' => null]);

        $this->actingAs($master)->get(route('accounting'))->assertOk()
            ->assertSeeInOrder(['Total ORS Amount', '3,000.00'])
            ->assertSeeInOrder(['DVs Awaiting Payment', '1,000.00'])
            ->assertSeeInOrder(['DVs Paid', '2,400.00'])
            ->assertSeeInOrder(['Advances Recorded (non-cash)', '50,000.00']);
    }

    public function test_the_cash_cards_count_the_cash_paid_out_net_of_the_tax_a_dv_withholds(): void
    {
        $organization = Organization::create(['name' => 'cash-cards', 'slug' => 'cash-cards', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'CCD', 'name' => 'Cash Cards School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $make = fn (string $number, float $amount, ?string $paid, array $deductions = []) => LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number,
            'ors_kind' => 'other', 'amount' => $amount, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase',
            'dv_number' => '2026-03-'.$number, 'dv_date' => '2026-03-12', 'dv_non_cash' => false, 'paid_at' => $paid, 'dv_deductions' => $deductions,
        ]);
        $make('0001', 1000, '2026-03-20', ['withholding_tax' => 100]);
        $make('0002', 500, null, ['withholding_tax' => 50]);

        $this->actingAs($master)->get(route('cash'))->assertOk()
            ->assertSeeInOrder(['Payable Amount', '450.00'])
            ->assertSeeInOrder(['Total Disbursed', '900.00']);
    }
}
