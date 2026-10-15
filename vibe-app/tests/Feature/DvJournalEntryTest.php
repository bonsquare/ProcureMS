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

    public function test_the_dv_preview_draws_the_official_voucher_even_before_the_journal_is_finished_and_saves_nothing(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $this->actingAs($master);
        $form = ['dv_date' => now()->toDateString(), 'payee' => 'Preview Supplier', 'dv_particulars' => 'Preview particulars', 'payment_mode' => 'MDS Check'];

        $unfinished = $this->post(route('accounting.dv.preview', $report), $form + ['journal' => [['account_code' => '', 'debit' => '10000', 'credit' => '']]])->assertOk()->getContent();
        $this->assertStringContainsString('Appendix 32', $unfinished);
        $this->assertStringContainsString('Preview Supplier', $unfinished);

        $finished = $this->post(route('accounting.dv.preview', $report), $form + ['journal' => [
            ['account_code' => $c['expense'], 'debit' => '10000', 'credit' => ''],
            ['account_code' => $c['cash'], 'debit' => '', 'credit' => '10000'],
        ]])->assertOk()->getContent();
        $this->assertStringContainsString($c['expense'], $finished);

        $this->assertNull($report->fresh()->dv_number, 'a preview is rolled back');
        $this->assertSame(0, DvJournalLine::count());

        $this->post(route('accounting.dv.store', $report), $form + ['journal' => [['account_code' => '', 'debit' => '10000', 'credit' => '']]])->assertSessionHasErrors();
        $this->assertNull($report->fresh()->dv_number, 'saving still needs the finished journal entry');
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
            'too many lines' => array_merge([['account_code' => $c['expense'], 'debit' => '10000']], array_fill(0, 20, ['account_code' => $c['cash'], 'credit' => '500'])),
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
        $this->assertStringContainsString('y="460"', $html, 'the first entry');
        $this->assertStringContainsString('y="475"', $html, 'the second entry follows the first, close to it');
        $this->assertStringContainsString('dominant-baseline="central"', $html);
        $this->assertMatchesRegularExpression('/justify-content:flex-start[^>]*font:bold [0-9.]+px[^>]*>Journal Supplier<\/div>/', $html, 'the payee is bold, left aligned and centered vertically in its box');
    }

    public function test_a_dv_takes_ten_entry_lines_and_the_form_grows_to_hold_them(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $journal = [];
        for ($i = 0; $i < 9; $i++) {
            $journal[] = ['account_code' => $c['expense'], 'debit' => $i === 0 ? '2000' : '1000'];
        }
        $journal[] = ['account_code' => $c['cash'], 'credit' => '10000'];
        $form = ['dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check'];

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $form + ['journal' => array_merge($journal, array_fill(0, 11, ['account_code' => $c['cash'], 'credit' => '1']))])->assertSessionHasErrors('journal');
        $this->post(route('accounting.dv.store', $report), $form + ['journal' => $journal]);
        $this->assertSame(10, $report->fresh()->journalLines()->count());

        $html = $this->get(route('accounting.dv.print', $report->fresh()))->assertOk()->getContent();
        $this->assertStringContainsString('y="459"', $html, 'the first of ten lines');
        $this->assertStringContainsString('y="544.5"', $html, 'the tenth line');
        $this->assertStringContainsString('translate(0 50.82)', $html, 'the rest of the form moves down to make room');
        $this->assertStringNotContainsString('translate(0 -', $html, 'ten lines still fit without cutting the Particulars');
    }

    public function test_the_deductions_of_a_dv_are_saved_and_printed_above_the_amount_due(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $form = ['dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000']]];

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $form + ['deductions' => ['withholding_tax' => '10000']])->assertSessionHasErrors('deductions');
        $this->assertNull($report->fresh()->dv_number);

        $this->post(route('accounting.dv.store', $report), $form + ['deductions' => ['withholding_tax' => '500', 'liquidated_damages' => '', 'retention' => '250', 'mobilization' => '0']])->assertSessionHasNoErrors();
        $this->assertEquals(['withholding_tax' => 500, 'retention' => 250], $report->fresh()->dv_deductions);

        $html = $this->get(route('accounting.dv.print', $report->fresh()))->assertOk()->getContent();
        $this->assertStringContainsString('Less:', $html);
        $this->assertStringContainsString('Withholding Tax', $html);
        $this->assertStringContainsString('Retention', $html);
        $this->assertStringNotContainsString('Mobilization', $html, 'only the deductions that were typed are printed');
        $this->assertStringContainsString('₱9,250.00', $html, 'Amount Due is the amount less the deductions');
    }

    public function test_a_deduction_not_in_the_list_can_be_added_by_name(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $form = ['dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000']]];

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $form + ['deductions_other' => [['label' => '', 'amount' => '100']]])->assertSessionHasErrors('deductions');
        $this->post(route('accounting.dv.store', $report), $form + ['deductions_other' => array_fill(0, 3, ['label' => 'Penalty', 'amount' => '10'])])->assertSessionHasErrors('deductions_other');
        $this->assertNull($report->fresh()->dv_number);

        $this->post(route('accounting.dv.store', $report), $form + ['deductions' => ['retention' => '250'], 'deductions_other' => [['label' => 'Penalty', 'amount' => '300']]])->assertSessionHasNoErrors();

        $html = $this->get(route('accounting.dv.print', $report->fresh()))->assertOk()->getContent();
        $this->assertStringContainsString('Penalty', $html);
        $this->assertStringContainsString('₱9,450.00', $html, 'Amount Due is the amount less every deduction');
        $this->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee('id="deduction-add"', false);
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

    public function test_the_cash_page_opens_without_the_journal_account_list(): void
    {
        [, $master] = $this->ors(100);

        $this->actingAs($master)->get(route('cash'))->assertOk()->assertSee('Cash');
    }

    public function test_the_accounting_tab_shows_counts_search_and_the_journal_summary(): void
    {
        [$report, $master] = $this->ors(10000);
        $c = $this->codes();
        $this->actingAs($master);

        $this->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee('id="row-search"', false)->assertSee('Create DV')->assertSee('Journal Supplier')->assertSee('border-l-4', false);

        $this->post(route('accounting.dv.store', $report), [
            'dv_date' => now()->toDateString(), 'payee' => 'Journal Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $c['expense'], 'debit' => '10000'], ['account_code' => $c['cash'], 'credit' => '10000']],
        ]);

        $this->get(route('accounting', ['tab' => 'with_dv']))->assertOk()->assertSee('Journal entry · 2 lines')->assertSee('Cash-Modified Disbursement System')->assertSee('Dr')->assertSee('Cr')->assertSee('DV-');
    }
}
