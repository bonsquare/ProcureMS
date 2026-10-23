<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\DvBreakdownLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DvBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function ors(): array
    {
        $organization = Organization::create(['name' => 'bd', 'slug' => 'bd', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'BD', 'name' => 'Breakdown School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-BD-1', 'ors_number' => 'ORS-BD-1', 'ors_kind' => 'other',
            'amount' => 10000, 'status' => 'approved', 'payee' => 'Breakdown Supplier', 'purpose' => 'Advance',
        ]);

        return [$report, $master, $school];
    }

    private function dvForm(array $extra = []): array
    {
        $accounts = collect(ChartOfAccount::standardAccounts())->where('category', 'MOOE')->pluck('code')->values();

        return $extra + [
            'dv_date' => now()->toDateString(), 'payee' => 'Breakdown Supplier', 'dv_particulars' => 'Advance', 'payment_mode' => 'MDS Check',
            'journal' => [
                ['account_code' => $accounts[0], 'debit' => '10000', 'credit' => ''],
                ['account_code' => '1010404000', 'debit' => '', 'credit' => '10000'],
            ],
        ];
    }

    private function mooe(): array
    {
        return collect(ChartOfAccount::standardAccounts())->where('category', 'MOOE')->pluck('code')->values()->all();
    }

    public function test_the_breakdown_is_saved_with_the_dv_and_replaced_on_edit(): void
    {
        [$report, $master] = $this->ors();
        [$a, $b] = $this->mooe();
        $this->actingAs($master);

        $this->post(route('accounting.dv.store', $report), $this->dvForm(['breakdown' => [
            ['account_code' => $a, 'amount' => '6000'], ['account_code' => $b, 'amount' => '4000'], ['account_code' => '', 'amount' => ''],
        ]]))->assertRedirect();

        $lines = DvBreakdownLine::where('liquidation_report_id', $report->id)->orderBy('line_no')->get();
        $this->assertSame([$a, $b], $lines->pluck('account_code')->all());
        $this->assertSame(10000.0, (float) $lines->sum('amount'));
        $this->assertSame($report->organization_id, $lines[0]->organization_id);

        $this->post(route('accounting.dv.store', $report->fresh()), $this->dvForm(['reason' => 'Correction', 'password' => 'password', 'breakdown' => [['account_code' => $b, 'amount' => '10000']]]));
        $this->assertSame([$b], DvBreakdownLine::where('liquidation_report_id', $report->id)->pluck('account_code')->all());
    }

    public function test_a_breakdown_that_is_not_the_dv_amount_or_has_an_unknown_account_is_refused(): void
    {
        [$report, $master] = $this->ors();
        [$a] = $this->mooe();
        $this->actingAs($master);

        foreach ([[['account_code' => $a, 'amount' => '9000']], [['account_code' => '0000000000', 'amount' => '10000']], [['account_code' => $a, 'amount' => '']]] as $breakdown) {
            $this->post(route('accounting.dv.store', $report), $this->dvForm(['breakdown' => $breakdown]))->assertSessionHasErrors('breakdown');
        }
        $this->assertNull($report->fresh()->dv_number);
        $this->assertSame(0, DvBreakdownLine::count());
    }

    public function test_a_dv_with_no_breakdown_is_saved_as_before(): void
    {
        [$report, $master] = $this->ors();
        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->dvForm())->assertRedirect();

        $this->assertNotNull($report->fresh()->dv_number);
        $this->assertSame(0, DvBreakdownLine::count());
    }

    public function test_the_cash_report_splits_a_payment_by_its_breakdown_and_falls_back_without_one(): void
    {
        [$report, $master, $school] = $this->ors();
        [$a, $b] = $this->mooe();
        $this->actingAs($master);
        $report->update(['dv_number' => 'DV-1', 'dv_date' => '2026-09-29', 'payment_mode' => 'MDS Check', 'payment_reference' => '0001', 'paid_at' => '2026-09-29']);
        $build = fn () => app(CashReportService::class)->build($school, Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30'))['lines']->first();

        $this->assertSame([], $build()['splits']);

        foreach ([[$a, 6000], [$b, 4000]] as $number => [$code, $amount]) {
            DvBreakdownLine::create(['organization_id' => $report->organization_id, 'liquidation_report_id' => $report->id, 'line_no' => $number + 1, 'account_code' => $code, 'account_title' => 'T', 'amount' => $amount]);
        }

        $this->assertSame([$a => 6000.0, $b => 4000.0], $build()['splits']);
    }
}
