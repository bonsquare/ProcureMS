<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DvNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
    }

    public function test_a_dv_is_numbered_by_year_and_month_with_a_number_that_runs_through_the_year(): void
    {
        $organization = $this->organization('dv-a');
        $other = $this->organization('dv-b');
        $numbers = app(DocumentNumberService::class);
        $next = fn (Organization $organization, string $date) => $numbers->next($organization->id, 'disbursement_voucher', 'DV', date: $date);

        $this->assertSame('2026-09-0001', $next($organization, '2026-09-05'));
        $this->assertSame('2026-09-0002', $next($organization, '2026-09-28'));
        $this->assertSame('2026-10-0003', $next($organization, '2026-10-02'));
        $this->assertSame('2026-10-0001', $next($other, '2026-10-02'), 'each organization has its own series');
        $this->assertSame('2027-01-0001', $next($organization, '2027-01-04'), 'a new year starts again');
        $this->assertSame('2026-10-0004', $numbers->preview($organization->id, 'disbursement_voucher', 'DV', 2026, date: '2026-10-20'));
    }

    public function test_the_earlier_dv_numbers_still_count_toward_the_running_number_and_other_documents_keep_their_format(): void
    {
        $organization = $this->organization('dv-c');
        $school = School::create(['organization_id' => $organization->id, 'code' => 'DVC', 'name' => 'DV C School', 'status' => 'active']);
        LiquidationReport::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-1', 'ors_number' => 'ORS-2026-0007', 'dv_number' => 'DV-2026-0003', 'amount' => 100, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase']);
        Carbon::setTestNow('2026-11-06');

        $numbers = app(DocumentNumberService::class);

        $this->assertSame('2026-11-0004', $numbers->next($organization->id, 'disbursement_voucher', 'DV'));
        $this->assertSame('ORS-2026-0008', $numbers->next($organization->id, 'obligation_request', 'ORS'));
    }

    public function test_a_new_dv_takes_the_year_and_month_of_its_dv_date_not_of_the_day_it_is_entered(): void
    {
        Carbon::setTestNow('2026-11-06');
        $organization = $this->organization('dv-d');
        $school = School::create(['organization_id' => $organization->id, 'code' => 'DVD', 'name' => 'DV D School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $report = fn (string $number) => LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-'.$number, 'ors_number' => 'ORS-'.$number,
            'ors_kind' => 'other', 'amount' => 5000, 'status' => 'approved', 'payee' => 'Store', 'purpose' => 'Purchase',
        ]);
        $journal = [['account_code' => '1010404000', 'debit' => '5000', 'credit' => ''], ['account_code' => '1990101000', 'debit' => '', 'credit' => '5000']];
        $post = fn (LiquidationReport $report, array $extra) => $this->actingAs($master)->post(route('accounting.dv.store', $report), $extra + [
            'payee' => 'Store', 'dv_particulars' => 'Advances', 'payment_mode' => 'MDS Check', 'dv_non_cash' => '1', 'journal' => $journal,
        ])->assertSessionHasNoErrors();

        $first = $report('1');
        $post($first, ['dv_date' => '2026-03-12']);
        $second = $report('2');
        $post($second, ['dv_date' => '2026-04-02', 'dv_number' => '2026-11-0002']);
        $third = $report('3');
        $post($third, ['dv_date' => '2026-04-03', 'dv_number' => 'DV-77']);

        $this->assertSame('2026-03-0001', $first->fresh()->dv_number);
        $this->assertSame('2026-04-0002', $second->fresh()->dv_number, 'a suggested number of another month takes the month of the DV date');
        $this->assertSame('DV-77', $third->fresh()->dv_number, 'a number typed by hand is kept');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
