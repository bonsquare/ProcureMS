<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DvJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function ors(float $amount = 10000): array
    {
        $organization = Organization::create(['name' => 'jv', 'slug' => 'jv', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'JV', 'name' => 'Journal School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-JV-1', 'ors_number' => 'ORS-JV-1',
            'amount' => $amount, 'status' => 'approved', 'payee' => 'Journal Supplier', 'purpose' => 'Office supplies',
        ]);

        return [$report, $master, $organization];
    }

    private function codes(): array
    {
        $accounts = collect(ChartOfAccount::standardAccounts());

        return [
            'expense' => $accounts->firstWhere('category', 'MOOE')['code'],
            'cash' => '1010404000',
            'tax' => '2020101000',
        ];
    }

    public function test_a_balanced_double_entry_is_saved_with_the_dv(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();

        $this->actingAs($master)->post(route('accounting.dv.store', $report), [
            'dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [
                ['account_code' => $c['expense'], 'debit' => '10000', 'credit' => ''],
                ['account_code' => $c['cash'], 'debit' => '', 'credit' => '9500'],
                ['account_code' => $c['tax'], 'debit' => '', 'credit' => '500'],
            ],
        ])->assertRedirect();

        $report->refresh();
        $this->assertNotNull($report->dv_number);
        $lines = DvJournalLine::where('liquidation_report_id', $report->id)->orderBy('line_no')->get();
        $this->assertCount(3, $lines);
        $this->assertSame([1, 2, 3], $lines->pluck('line_no')->all());
        $this->assertSame([$c['expense'], $c['cash'], $c['tax']], $lines->pluck('account_code')->all());
        $this->assertSame('Cash-Modified Disbursement System (MDS), Regular', $lines[1]->account_title);
        $this->assertSame('Due to BIR', $lines[2]->account_title);
        $this->assertSame(10000.0, (float) $lines->sum('debit'));
        $this->assertSame(10000.0, (float) $lines->sum('credit'));
        $this->assertSame($report->organization_id, $lines[0]->organization_id);
    }

    public function test_an_entry_that_does_not_balance_is_refused_and_nothing_is_saved(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $this->actingAs($master);

        $cases = [
            'out of balance' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '9000']],
            'not the DV amount' => [['account_code' => $c['expense'], 'debit' => '9000'], ['account_code' => $c['cash'], 'credit' => '9000']],
            'one line only' => [['account_code' => $c['expense'], 'debit' => '10000']],
            'debit and credit on one line' => [['account_code' => $c['expense'], 'debit' => '5000', 'credit' => '5000'], ['account_code' => $c['cash'], 'debit' => '5000', 'credit' => '5000']],
            'empty line' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000'], ['account_code' => $c['tax']]],
            'unknown account' => [['account_code' => '0000000000', 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000']],
            'too many lines' => array_merge([['account_code' => $c['expense'], 'debit' => '10000']], array_fill(0, 4, ['account_code' => $c['cash'], 'credit' => '2500'])),
            'no entry' => null,
        ];

        foreach ($cases as $name => $journal) {
            $this->post(route('accounting.dv.store', $report), [
                'dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
                ...($journal === null ? [] : ['journal' => $journal]),
            ])->assertSessionHasErrors('journal');
            $this->assertNull($report->fresh()->dv_number, $name);
            $this->assertSame(0, DvJournalLine::count(), $name);
        }
    }

    public function test_the_printed_dv_shows_the_accounting_entry(): void
    {
        [$report, $master, $organization] = $this->ors(10000);
        $c = $this->codes();
        $this->actingAs($master)->post(route('accounting.dv.store', $report), [
            'dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000']],
        ]);

        $html = $this->get(route('accounting.dv.print', $report->fresh()))->assertOk()->getContent();
        $this->assertStringContainsString('Cash-Modified Disbursement System (MDS), Regular', $html);
        $this->assertStringContainsString($c['cash'], $html);
        $this->assertStringContainsString('10,000.00', $html);
    }

    public function test_a_dv_without_entries_still_prints(): void
    {
        [$report, $master] = $this->ors(500);
        $report->update(['dv_number' => 'DV-OLD-1', 'dv_date' => now(), 'payee' => 'Old Payee', 'dv_particulars' => 'Old', 'payment_mode' => 'MDS Check']);

        $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk();
    }

    public function test_the_create_dv_window_has_the_journal_section_and_suggestions(): void
    {
        [$report, $master] = $this->ors(12500);

        $this->actingAs($master)->get(route('accounting', ['tab' => 'for_dv']))->assertOk()
            ->assertSee('Journal entry')->assertSee('Debit')->assertSee('Credit')->assertSee('id="journal-rows"', false)
            ->assertSee('data-amount-raw="12500.00"', false)->assertSee('Cash-Modified Disbursement System (MDS), Regular');
    }

    public function test_the_finance_menu_is_in_the_sidebar_of_every_page(): void
    {
        [, $master] = $this->ors(100);

        foreach (['procurement', 'school-settings', 'school-management', 'transfer-requests'] as $page) {
            $this->actingAs($master)->get(route($page, $page === 'school-settings' ? ['ui' => 'staff-save-v7'] : []))->assertOk()
                ->assertSee('Finance')->assertSee(route('budget'), false)->assertSee(route('accounting'), false)->assertSee(route('chart-of-accounts'), false)
                ->assertSee(route('allotment-registry'), false)->assertSee(route('cash'), false);
        }
    }
}
