<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DvFundClusterTest extends TestCase
{
    use RefreshDatabase;

    private function ors(): array
    {
        $organization = Organization::create(['name' => 'fc', 'slug' => 'fc', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'FC', 'name' => 'Fund School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-FC-1', 'ors_number' => 'ORS-FC-1',
            'amount' => 5000, 'status' => 'approved', 'payee' => 'Fund Supplier', 'purpose' => 'Supplies', 'source_of_fund' => 'MOOE',
        ]);

        return [$report, $master];
    }

    private function form(array $overrides = []): array
    {
        $expense = collect(ChartOfAccount::standardAccounts())->firstWhere('category', 'MOOE')['code'];

        return array_replace([
            'dv_date' => now()->toDateString(), 'payee' => 'Fund Supplier', 'dv_particulars' => 'Supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $expense, 'debit' => '5000', 'credit' => ''], ['account_code' => '1010404000', 'debit' => '', 'credit' => '5000']],
        ], $overrides);
    }

    public function test_the_fund_cluster_typed_on_the_dv_is_printed_and_the_ors_fund_is_the_fallback(): void
    {
        [$report, $master] = $this->ors();
        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->form(['dv_fund_cluster' => 'Fund 101']))->assertSessionHasNoErrors();

        $this->assertSame('Fund 101', $report->fresh()->dv_fund_cluster);
        $this->get(route('accounting.dv.print', $report))->assertOk()->assertSee('>Fund 101</text>', false);

        $report->update(['dv_fund_cluster' => null]);
        $this->get(route('accounting.dv.print', $report))->assertSee('>MOOE</text>', false);
    }

    public function test_the_printed_dv_underlines_the_header_labels_and_has_the_bank_name_in_the_top_row_of_receipt_of_payment(): void
    {
        [$report, $master] = $this->ors();
        $report->update(['dv_number' => 'DV-FC-1', 'dv_date' => now()->toDateString()]);
        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        foreach (['Fund Cluster :', 'Date :', 'DV No. :'] as $label) {
            $this->assertMatchesRegularExpression('/font-weight="bold" text-decoration="underline"[^>]*>'.preg_quote($label, '/').'<\/text>/', $html);
        }
        $this->assertMatchesRegularExpression('/x="170" y="688\.5"[^>]*>Bank Name &amp; Account Number:<\/text>/', $html);
    }
}
