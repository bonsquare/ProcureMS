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
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-FC-1', 'ors_number' => 'ORS-FC-1', 'ors_kind' => 'other',
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

    public function test_the_fund_cluster_typed_on_the_dv_is_printed_and_general_fund_is_the_default(): void
    {
        [$report, $master] = $this->ors();
        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->form(['dv_fund_cluster' => 'Fund 101']))->assertSessionHasNoErrors();

        $this->assertSame('Fund 101', $report->fresh()->dv_fund_cluster);
        $this->get(route('accounting.dv.print', $report))->assertOk()->assertSee('>Fund 101</text>', false);

        $report->update(['dv_fund_cluster' => null]);
        $this->get(route('accounting.dv.print', $report))->assertSee('>General Fund</text>', false);
    }

    public function test_the_printed_dv_header_has_plain_labels_and_bold_underlined_data_and_has_the_bank_name_raised_in_its_own_cell(): void
    {
        [$report, $master] = $this->ors();
        $report->update(['dv_number' => 'DV-FC-1', 'dv_date' => now()->toDateString()]);
        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        foreach (['Fund Cluster :', 'Date :', 'DV No. :'] as $label) {
            $this->assertMatchesRegularExpression('/font-size="10\.5" xml:space="preserve">'.preg_quote($label, '/').'<\/text>/', $html);
        }
        foreach (['General Fund', now()->format('F d, Y'), 'DV-FC-1'] as $value) {
            $this->assertMatchesRegularExpression('/font-weight="bold" text-decoration="underline">'.preg_quote($value, '/').'<\/text>/', $html);
        }
        $this->assertMatchesRegularExpression('/x="316\.07" y="737"[^>]*>Printed Name:<\/text>/', $html);
        $this->assertStringNotContainsString('>Fund Supplier</text>', $html);
        $this->assertMatchesRegularExpression('/x="316\.07" y="707"[^>]*>Bank Name &amp; Account Number:<\/text>/', $html);
    }

    public function test_a_long_fund_cluster_takes_two_lines_in_its_cell(): void
    {
        [$report, $master] = $this->ors();
        $report->update(['dv_number' => 'DV-FC-1', 'dv_date' => now()->toDateString(), 'dv_fund_cluster' => 'Regular Agency Fund 101 Cash']);
        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/y="64"[^>]*>Regular Agency<\/text>/', $html);
        $this->assertMatchesRegularExpression('/y="75"[^>]*>Fund 101 Cash<\/text>/', $html);
    }

    public function test_a_short_fund_cluster_sits_on_the_line_of_its_label(): void
    {
        [$report, $master] = $this->ors();
        $report->update(['dv_number' => 'DV-FC-1', 'dv_date' => now()->toDateString()]);
        $html = $this->actingAs($master)->get(route('accounting.dv.print', $report))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/x="463" y="61"[^>]*>Fund Cluster :<\/text>/', $html);
        $this->assertMatchesRegularExpression('/x="527" y="61"[^>]*>General Fund<\/text>/', $html);
    }
}
