<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Services\CashReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DvNonCashTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_cash_dv_is_recorded_in_the_journal_only_and_never_waits_for_payment(): void
    {
        $organization = Organization::create(['name' => 'nc', 'slug' => 'nc', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'NC', 'name' => 'Non Cash School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-NC-1', 'ors_number' => 'ORS-NC-1', 'ors_kind' => 'other',
            'amount' => 5000, 'status' => 'approved', 'payee' => 'Cash-MDS', 'purpose' => 'Advances received',
        ]);

        $this->actingAs($master)->post(route('accounting.dv.store', $report), [
            'dv_date' => now()->toDateString(), 'payee' => 'Cash-MDS', 'dv_particulars' => 'Advances for operating expenses received', 'payment_mode' => 'MDS Check', 'dv_non_cash' => '1',
            'journal' => [
                ['account_code' => '1990101000', 'debit' => '5000', 'credit' => ''],
                ['account_code' => '1010404000', 'debit' => '', 'credit' => '5000'],
            ],
        ])->assertRedirect();

        $this->assertTrue($report->fresh()->dv_non_cash);
        $this->assertSame(2, $report->journalLines()->count());
        $this->get(route('cash'))->assertOk()->assertDontSee('Non Cash School');
        $this->post(route('cash.pay', $report), ['paid_at' => now()->toDateString(), 'payment_mode' => 'MDS Check', 'payment_reference' => '1'])->assertStatus(422);
        $data = app(CashReportService::class)->build($school, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(1, $data['receipts']->count(), 'the advances of a non-cash DV are cash received');
        $this->assertEquals(5000.0, (float) $data['receipts']->first()->amount);
        $this->assertSame('cash_advance', $data['receipts']->first()->kind);
    }
}
