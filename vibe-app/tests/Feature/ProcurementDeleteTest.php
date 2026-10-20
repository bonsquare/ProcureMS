<?php

namespace Tests\Feature;

use App\Models\CancelledCheck;
use App\Models\DvJournalLine;
use App\Models\LiquidationDocumentCheck;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProcurementDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-pass-123';

    /** @return array{0: ProcurementRequest, 1: User, 2: LiquidationReport} */
    private function requestWithEverything(string $slug): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => "School $slug", 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null, 'password' => Hash::make(self::PASSWORD)]);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'request_number' => "PR-$slug", 'title' => 'Supplies', 'amount' => 5000, 'status' => 'completed']);
        ProcurementRequestItem::create(['procurement_request_id' => $pr->id, 'organization_id' => $organization->id, 'name' => 'Paper', 'quantity' => 1, 'unit' => 'ream', 'unit_price' => 5000, 'total_price' => 5000]);
        ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'document_type' => 'rfq', 'document_number' => "RFQ-$slug", 'document_date' => now()->toDateString(), 'status' => 'saved']);
        $report = LiquidationReport::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'procurement_request_id' => $pr->id, 'report_number' => "LR-$slug", 'ors_number' => "ORS-$slug", 'amount' => 5000, 'status' => 'approved', 'dv_number' => "DV-$slug"]);
        LiquidationDocumentCheck::create(['organization_id' => $organization->id, 'liquidation_report_id' => $report->id, 'item_key' => 'supporting_paper', 'state' => 'received']);
        DvJournalLine::create(['organization_id' => $organization->id, 'liquidation_report_id' => $report->id, 'line_no' => 1, 'account_code' => '1', 'account_title' => 'A', 'debit' => 5000, 'credit' => 0]);
        CancelledCheck::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'liquidation_report_id' => $report->id, 'check_number' => '1', 'amount' => 5000, 'reason' => 'x', 'cancelled_by' => $master->id, 'cancelled_at' => now()]);

        return [$pr, $master, $report];
    }

    public function test_deleting_a_pr_removes_everything_made_from_it(): void
    {
        [$pr, $master, $report] = $this->requestWithEverything('del');

        $this->actingAs($master)->delete(route('procurement.destroy', $pr), ['password' => self::PASSWORD])
            ->assertRedirect(route('procurement.requests'));

        $this->assertDatabaseMissing('procurement_requests', ['id' => $pr->id]);
        foreach (['procurement_request_items', 'procurement_documents'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        foreach (['liquidation_reports', 'liquidation_document_checks', 'dv_journal_lines', 'cancelled_checks'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_wrong_password_deletes_nothing(): void
    {
        [$pr, $master] = $this->requestWithEverything('keep');

        $this->actingAs($master)->delete(route('procurement.destroy', $pr), ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('procurement_requests', ['id' => $pr->id]);
    }

    public function test_another_organization_cannot_delete_it(): void
    {
        [$pr] = $this->requestWithEverything('mine');
        $organization = Organization::create(['name' => 'other', 'slug' => 'other', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'OTH', 'name' => 'Other', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id, 'password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($outsider)->delete(route('procurement.destroy', $pr), ['password' => self::PASSWORD])->assertNotFound();

        $this->assertDatabaseHas('procurement_requests', ['id' => $pr->id]);
    }
}
