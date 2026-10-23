<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\DvBreakdownLine;
use App\Models\DvJournalLine;
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

    private function part(string $code, string $debit = '', string $credit = ''): array
    {
        return ['account_code' => $code, 'debit' => $debit, 'credit' => $credit];
    }

    public function test_the_breakdown_of_a_journal_line_is_saved_with_the_dv_and_replaced_on_edit(): void
    {
        [$report, $master] = $this->ors();
        [$a, $b] = $this->mooe();
        $this->actingAs($master);

        $this->post(route('accounting.dv.store', $report), $this->dvForm(['breakdown' => [
            0 => [$this->part($a, '7000'), $this->part($b, '4000'), $this->part($b, '', '1000'), $this->part('', '', '')],
        ]]))->assertRedirect();

        $lines = DvBreakdownLine::where('liquidation_report_id', $report->id)->orderBy('line_no')->get();
        $this->assertSame([$a, $b, $b], $lines->pluck('account_code')->all());
        $this->assertSame([1, 1, 1], $lines->pluck('journal_line_no')->all());
        $this->assertSame(11000.0, (float) $lines->sum('debit'));
        $this->assertSame(1000.0, (float) $lines->sum('credit'));
        $this->assertSame($report->organization_id, $lines[0]->organization_id);

        $this->post(route('accounting.dv.store', $report->fresh()), $this->dvForm(['reason' => 'Correction', 'password' => 'password', 'breakdown' => [0 => [$this->part($b, '10000')]]]));
        $this->assertSame([$b], DvBreakdownLine::where('liquidation_report_id', $report->id)->pluck('account_code')->all());
    }

    public function test_a_credit_line_can_be_broken_down_too(): void
    {
        [$report, $master] = $this->ors();
        [$a, $b] = $this->mooe();

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->dvForm(['breakdown' => [1 => [$this->part($a, '', '6000'), $this->part($b, '', '4000')]]]))->assertRedirect();

        $this->assertSame([2, 2], DvBreakdownLine::where('liquidation_report_id', $report->id)->pluck('journal_line_no')->all());
    }

    public function test_a_breakdown_that_does_not_net_to_its_line_or_is_malformed_is_refused(): void
    {
        [$report, $master] = $this->ors();
        [$a] = $this->mooe();
        $this->actingAs($master);

        $cases = [
            'not the line amount' => [0 => [$this->part($a, '9000')]],
            'credits reduce a debit line' => [0 => [$this->part($a, '10000'), $this->part($a, '', '500')]],
            'unknown account' => [0 => [$this->part('0000000000', '10000')]],
            'no amount' => [0 => [$this->part($a)]],
            'debit and credit together' => [0 => [$this->part($a, '10000', '10000')]],
            'a line that is not there' => [5 => [$this->part($a, '10000')]],
        ];
        foreach ($cases as $breakdown) {
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

    public function test_the_cash_report_splits_a_payment_by_the_net_of_its_breakdown_and_falls_back_without_one(): void
    {
        [$report, $master, $school] = $this->ors();
        [$a, $b] = $this->mooe();
        $this->actingAs($master);
        $report->update(['dv_number' => 'DV-1', 'dv_date' => '2026-09-29', 'payment_mode' => 'MDS Check', 'payment_reference' => '0001', 'paid_at' => '2026-09-29']);
        DvJournalLine::create(['organization_id' => $report->organization_id, 'liquidation_report_id' => $report->id, 'line_no' => 1, 'account_code' => '1990101000', 'account_title' => 'Advances', 'debit' => 10000, 'credit' => 0]);
        DvJournalLine::create(['organization_id' => $report->organization_id, 'liquidation_report_id' => $report->id, 'line_no' => 2, 'account_code' => '1010404000', 'account_title' => 'Cash', 'debit' => 0, 'credit' => 10000]);
        $build = fn () => app(CashReportService::class)->build($school, Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30'))['lines']->first();
        $part = fn (int $journal, int $number, string $code, float $debit, float $credit) => DvBreakdownLine::create(['organization_id' => $report->organization_id, 'liquidation_report_id' => $report->id, 'journal_line_no' => $journal, 'line_no' => $number, 'account_code' => $code, 'account_title' => 'T', 'debit' => $debit, 'credit' => $credit]);

        $this->assertSame([], $build()['splits']);

        $part(2, 1, $a, 0, 10000);
        $this->assertSame([], $build()['splits'], 'a breakdown of the credit side is not an expense split');

        $part(1, 2, $a, 6000, 0);
        $part(1, 3, $b, 5000, 0);
        $part(1, 4, $a, 0, 1000);
        $this->assertSame([$a => 5000.0, $b => 5000.0], $build()['splits']);
    }
}
