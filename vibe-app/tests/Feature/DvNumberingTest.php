<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
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
        $next = fn (Organization $organization, string $date) => (function () use ($numbers, $organization, $date) {
            Carbon::setTestNow($date);

            return $numbers->next($organization->id, 'disbursement_voucher', 'DV');
        })();

        $this->assertSame('2026-09-0001', $next($organization, '2026-09-05'));
        $this->assertSame('2026-09-0002', $next($organization, '2026-09-28'));
        $this->assertSame('2026-10-0003', $next($organization, '2026-10-02'));
        $this->assertSame('2026-10-0001', $next($other, '2026-10-02'), 'each organization has its own series');
        $this->assertSame('2027-01-0001', $next($organization, '2027-01-04'), 'a new year starts again');
        Carbon::setTestNow('2026-10-20');
        $this->assertSame('2026-10-0004', $numbers->preview($organization->id, 'disbursement_voucher', 'DV', 2026));
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
