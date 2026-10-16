<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\ChartOfAccount;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AllotmentRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CancelledCheckTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-pass-123';

    /** A paid ORS with a DV and a saved journal entry, and a master user who knows the password. */
    private function paidOrs(float $amount = 10000): array
    {
        $organization = Organization::create(['name' => 'cc', 'slug' => 'cc', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'CC', 'name' => 'Check School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null, 'password' => Hash::make(self::PASSWORD)]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-CC-1', 'ors_number' => 'ORS-CC-1',
            'amount' => $amount, 'status' => 'approved', 'payee' => 'Check Supplier', 'purpose' => 'Office supplies',
            'dv_number' => 'DV-CC-1', 'dv_date' => now()->toDateString(), 'dv_particulars' => 'Office supplies',
            'payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-1', 'paid_at' => now()->toDateString(), 'paid_by' => $master->id,
        ]);
        foreach ([[$this->codes()['expense'], $amount, 0], [$this->codes()['cash'], 0, $amount]] as $number => [$code, $debit, $credit]) {
            DvJournalLine::create(['organization_id' => $organization->id, 'liquidation_report_id' => $report->id, 'line_no' => $number + 1, 'account_code' => $code, 'account_title' => 'Account', 'debit' => $debit, 'credit' => $credit]);
        }

        return [$report, $master, $organization];
    }

    private function codes(): array
    {
        return ['expense' => collect(ChartOfAccount::standardAccounts())->firstWhere('category', 'MOOE')['code'], 'cash' => '1010404000'];
    }

    private function cancelled(float $amount = 10000): array
    {
        [$report, $master, $organization] = $this->paidOrs($amount);
        $this->actingAs($master)->post(route('cash.cancel', $report), ['reason' => 'Wrong amount', 'password' => self::PASSWORD]);

        return [$report->fresh(), $master, $organization];
    }

    private function orsEdit(array $overrides = []): array
    {
        return array_replace(['amount' => '9000', 'payee' => 'Check Supplier', 'purpose' => 'Office supplies', 'reason' => 'Corrected amount', 'password' => self::PASSWORD], $overrides);
    }

    private function dvForm(float $amount = 9000, array $overrides = []): array
    {
        $c = $this->codes();

        return array_replace([
            'dv_date' => now()->toDateString(), 'payee' => 'Check Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [
                ['account_code' => $c['expense'], 'debit' => (string) $amount, 'credit' => ''],
                ['account_code' => $c['cash'], 'debit' => '', 'credit' => (string) $amount],
            ],
        ], $overrides);
    }

    /** Cancelled, the ORS edited to 9000, approved again and its DV made again. */
    private function reissuedDv(): array
    {
        [$report, $master, $organization] = $this->cancelled(10000);
        $this->actingAs($master);
        $this->patch(route('accounting.ors.update', $report), $this->orsEdit())->assertSessionHasNoErrors();
        $this->patch(route('accounting.review', $report), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->post(route('accounting.dv.store', $report), $this->dvForm(9000, ['dv_number' => 'DV-CC-1']))->assertSessionHasNoErrors();

        return [$report->fresh(), $master, $organization];
    }

    public function test_cancelling_records_the_check_and_sends_the_ors_back_to_review_without_its_dv(): void
    {
        [$report, $master] = $this->paidOrs(10000);

        $this->actingAs($master)->post(route('cash.cancel', $report), ['reason' => 'Wrong amount on the check', 'password' => self::PASSWORD])
            ->assertRedirect(route('cash', ['tab' => 'cancelled']));

        $check = CancelledCheck::sole();
        $this->assertSame('CHK-1', $check->check_number);
        $this->assertSame('DV-CC-1', $check->dv_number);
        $this->assertSame('10000.00', $check->amount);
        $this->assertSame('Wrong amount on the check', $check->reason);
        $this->assertSame($master->id, $check->cancelled_by);

        $report->refresh();
        $this->assertNull($report->paid_at);
        $this->assertNull($report->payment_reference);
        $this->assertNull($report->paid_by);
        $this->assertNull($report->dv_number);
        $this->assertSame(0, DvJournalLine::count());
        $this->assertSame('for_review', $report->status);
        $this->assertNull($report->approved_at);
        $this->assertTrue($report->reissue_pending);
    }

    public function test_cancel_needs_a_reason_and_the_users_own_password(): void
    {
        [$report, $master] = $this->paidOrs();

        $this->actingAs($master)->post(route('cash.cancel', $report), ['reason' => '', 'password' => self::PASSWORD])->assertSessionHasErrors('reason');
        $this->post(route('cash.cancel', $report), ['reason' => 'Typo', 'password' => 'not-the-password'])->assertSessionHasErrors('password');

        $this->assertSame(0, CancelledCheck::count());
        $this->assertNotNull($report->fresh()->paid_at);
        $this->assertSame(2, DvJournalLine::count());
    }

    public function test_an_unpaid_ors_cannot_be_cancelled(): void
    {
        [$report, $master] = $this->paidOrs();
        $report->update(['paid_at' => null]);

        $this->actingAs($master)->post(route('cash.cancel', $report), ['reason' => 'Typo', 'password' => self::PASSWORD])->assertStatus(422);
        $this->assertSame(0, CancelledCheck::count());
    }

    public function test_the_ors_of_a_cancelled_check_can_be_edited_while_it_is_in_review(): void
    {
        [$report, $master] = $this->cancelled(10000);

        $this->actingAs($master)->patch(route('accounting.ors.update', $report), $this->orsEdit(['payee' => 'Other Supplier']))->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('9000.00', $report->amount);
        $this->assertSame('Other Supplier', $report->payee);
        $this->assertSame('for_review', $report->status);
    }

    public function test_the_ors_edit_needs_a_reason_and_the_users_own_password(): void
    {
        [$report, $master] = $this->cancelled(10000);

        $this->actingAs($master)->patch(route('accounting.ors.update', $report), $this->orsEdit(['reason' => '']))->assertSessionHasErrors('reason');
        $this->patch(route('accounting.ors.update', $report), $this->orsEdit(['password' => 'wrong-password']))->assertSessionHasErrors('password');

        $this->assertSame('10000.00', $report->fresh()->amount);
    }

    public function test_an_ors_that_was_not_cancelled_cannot_be_edited_this_way(): void
    {
        [$report, $master] = $this->paidOrs();

        $this->actingAs($master)->patch(route('accounting.ors.update', $report), $this->orsEdit())->assertStatus(422);
        $this->assertSame('10000.00', $report->fresh()->amount);
    }

    public function test_after_the_edit_the_ors_is_approved_and_the_dv_is_made_again_with_the_same_number(): void
    {
        [$report] = $this->reissuedDv();

        $this->assertSame('DV-CC-1', $report->dv_number);
        $this->assertSame('9000.00', $report->amount);
        $this->assertSame('approved', $report->status);
        $this->assertSame(2, DvJournalLine::count());
        $this->assertSame('9000.00', $report->journalLines->first()->debit);
        $this->assertTrue($report->reissue_pending);
    }

    public function test_a_dv_that_is_not_paid_can_be_edited_with_a_reason_and_password(): void
    {
        [$report, $master] = $this->reissuedDv();
        $c = $this->codes();
        $corrected = $this->dvForm(9000, ['dv_particulars' => 'Corrected particulars', 'journal' => [
            ['account_code' => $c['expense'], 'debit' => '9000', 'credit' => ''],
            ['account_code' => $c['cash'], 'debit' => '', 'credit' => '8500'],
            ['account_code' => '2020101000', 'debit' => '', 'credit' => '500'],
        ]]);

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $corrected + ['reason' => '', 'password' => self::PASSWORD])->assertSessionHasErrors('reason');
        $this->post(route('accounting.dv.store', $report), $corrected + ['reason' => 'Tax missed', 'password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertSame(2, DvJournalLine::count());

        $this->post(route('accounting.dv.store', $report), $corrected + ['reason' => 'Tax missed', 'password' => self::PASSWORD])->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('DV-CC-1', $report->dv_number);
        $this->assertSame('Corrected particulars', $report->dv_particulars);
        $this->assertSame(3, DvJournalLine::count());
    }

    public function test_an_edit_whose_journal_does_not_match_the_amount_keeps_the_old_lines(): void
    {
        [$report, $master] = $this->reissuedDv();

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->dvForm(10000) + ['reason' => 'Typo', 'password' => self::PASSWORD])->assertSessionHasErrors('journal');

        $this->assertSame(2, DvJournalLine::count());
        $this->assertSame('9000.00', $report->fresh()->journalLines->first()->debit);
    }

    public function test_a_paid_dv_cannot_be_edited_and_paying_again_locks_the_ors(): void
    {
        [$report, $master] = $this->reissuedDv();

        $this->actingAs($master)->post(route('cash.pay', $report), ['payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-2', 'paid_at' => now()->toDateString()])->assertRedirect();

        $report->refresh();
        $this->assertFalse($report->reissue_pending);
        $this->assertSame('CHK-2', $report->payment_reference);
        $this->assertSame(1, CancelledCheck::count());
        $this->post(route('accounting.dv.store', $report), $this->dvForm(9000) + ['reason' => 'Late', 'password' => self::PASSWORD])->assertStatus(422);
    }

    public function test_the_register_and_the_screens_show_the_new_flow(): void
    {
        [$report, $master] = $this->paidOrs();
        $this->actingAs($master)->get(route('cash', ['tab' => 'paid']))->assertOk()->assertSee(route('cash.cancel', $report));

        $this->post(route('cash.cancel', $report), ['reason' => 'Wrong amount', 'password' => self::PASSWORD]);
        $this->get(route('cash', ['tab' => 'cancelled']))->assertOk()->assertSee('CHK-1')->assertSee('DV-CC-1')->assertSee('Wrong amount')->assertSee('₱10,000.00');
        $this->get(route('accounting', ['tab' => 'for_review']))->assertOk()->assertSee('Edit ORS')->assertSee(route('accounting.ors.update', $report));

        $this->patch(route('accounting.review', $report), ['status' => 'approved']);
        $this->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee('data-dv-number="DV-CC-1"', false)->assertSee('data-keep-signatories', false);

        $this->post(route('accounting.dv.store', $report), $this->dvForm(10000, ['dv_number' => 'DV-CC-1']));
        $this->get(route('accounting', ['tab' => 'with_dv']))->assertOk()->assertSee('Edit DV')->assertDontSee('Journal entry ·')->assertDontSee('Signatories</summary>', false);
    }

    public function test_the_allotment_registry_follows_the_cancel_and_the_new_amount(): void
    {
        [$report, $master, $organization] = $this->paidOrs(10000);
        $line = BudgetAllocation::create(['organization_id' => $organization->id, 'school_id' => $report->school_id, 'fiscal_year' => now()->year, 'source_of_fund' => 'MOOE', 'particulars' => 'Supplies', 'amount' => 50000, 'q1_amount' => 12500, 'q2_amount' => 12500, 'q3_amount' => 12500, 'q4_amount' => 12500]);
        $report->update(['budget_allocation_id' => $line->id, 'submitted_at' => now()]);
        $totals = fn () => app(AllotmentRegistryService::class)->buildLine($line->fresh(), 0)['totals'];

        $this->assertSame(10000.0, $totals()['disbursement']);

        $this->actingAs($master)->post(route('cash.cancel', $report), ['reason' => 'Wrong amount', 'password' => self::PASSWORD]);
        $this->assertSame(0.0, $totals()['disbursement']);
        $this->assertSame(10000.0, $totals()['obligation']);

        $this->patch(route('accounting.ors.update', $report), $this->orsEdit())->assertSessionHasNoErrors();
        $this->patch(route('accounting.review', $report), ['status' => 'approved']);
        $this->post(route('accounting.dv.store', $report), $this->dvForm(9000, ['dv_number' => 'DV-CC-1']))->assertSessionHasNoErrors();
        $this->post(route('cash.pay', $report), ['payment_mode' => 'MDS Check', 'payment_reference' => 'CHK-2', 'paid_at' => now()->toDateString()]);
        $this->assertSame(9000.0, $totals()['obligation']);
        $this->assertSame(9000.0, $totals()['disbursement']);
    }

    public function test_cancel_needs_cash_pay_and_the_ors_edit_needs_accounting_approve(): void
    {
        [$report] = $this->paidOrs();
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $report->organization_id, 'school_id' => $report->school_id, 'password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($viewer)->post(route('cash.cancel', $report), ['reason' => 'x', 'password' => self::PASSWORD])->assertForbidden();
        $this->assertNotNull($report->fresh()->paid_at);

        Subscription::create(['organization_id' => $report->organization_id, 'school_id' => $report->school_id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $cashier = User::factory()->create(['role' => 'cashier', 'organization_id' => $report->organization_id, 'school_id' => $report->school_id, 'password' => Hash::make(self::PASSWORD)]);
        $this->actingAs($cashier)->post(route('cash.cancel', $report), ['reason' => 'Wrong amount', 'password' => self::PASSWORD])->assertRedirect();
        $this->patch(route('accounting.ors.update', $report), $this->orsEdit())->assertForbidden();
        $this->assertSame('10000.00', $report->fresh()->amount);
    }

    public function test_another_organization_cannot_cancel(): void
    {
        [$report] = $this->paidOrs();
        $other = Organization::create(['name' => 'other', 'slug' => 'other', 'status' => 'active']);
        $school = School::create(['organization_id' => $other->id, 'code' => 'OT', 'name' => 'Other School', 'status' => 'active']);
        $intruder = User::factory()->create(['role' => 'school_admin', 'organization_id' => $other->id, 'school_id' => $school->id, 'password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($intruder)->post(route('cash.cancel', $report), ['reason' => 'Typo', 'password' => self::PASSWORD])->assertNotFound();
        $this->assertSame(0, CancelledCheck::count());
        $this->assertNotNull($report->fresh()->paid_at);
    }

    public function test_the_dialogs_do_not_let_the_browser_fill_the_reason_or_the_password(): void
    {
        [$report, $master] = $this->reissuedDv();
        $accounting = $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()->getContent();

        $this->assertStringContainsString('Confirm the change', $accounting);
        $this->assertStringNotContainsString('current-password', $accounting);
        $this->assertStringContainsString('autocomplete="new-password" readonly', $accounting);
        $this->assertStringContainsString('name="reason" required maxlength="255" autocomplete="off" readonly', $accounting);

        $cash = $this->get(route('cash', ['tab' => 'paid']))->assertOk()->getContent();
        $this->assertStringNotContainsString('current-password', $cash);
    }

    public function test_the_edit_dv_window_shows_the_official_dv_beside_the_form_and_previews_without_saving(): void
    {
        [$report, $master] = $this->reissuedDv();
        $this->actingAs($master)->get(route('accounting', ['tab' => 'with_dv']))->assertOk()
            ->assertSee('data-ors-edit data-split data-preview="'.route('accounting.dv.preview', $report).'"', false)
            ->assertSee('Edit Disbursement Voucher')->assertSee('Official DV (updates as you edit)');

        $html = $this->post(route('accounting.dv.preview', $report), $this->dvForm(9000, ['dv_particulars' => 'Preview only particulars']))->assertOk()->getContent();

        $this->assertStringContainsString('Preview only particulars', $html);
        $this->assertSame('Office supplies', $report->fresh()->dv_particulars);
        $this->assertSame(2, DvJournalLine::count());
    }
}
