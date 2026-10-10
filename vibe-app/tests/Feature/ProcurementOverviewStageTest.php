<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementOverviewStageTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Stage Org', 'slug' => 'stage-org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'STG', 'name' => 'Stage School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function request(array $tenant, string $number, string $status, array $documentTypes = []): ProcurementRequest
    {
        [$organization, $school, $user] = $tenant;
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => $number, 'title' => $number.' supplies', 'amount' => 1500, 'status' => $status, 'requested_at' => now()]);
        foreach ($documentTypes as $index => $type) {
            ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $user->id, 'document_type' => $type, 'document_number' => 'DOC-'.$number.'-'.$index, 'document_date' => now()->toDateString(), 'status' => 'prepared']);
        }

        return $request;
    }

    private function metrics(User $user): array
    {
        return $this->actingAs($user)->get(route('procurement'))->assertOk()->viewData('procurementMetrics');
    }

    public function test_a_request_with_an_abstract_shows_its_award_stage_not_for_canvass(): void
    {
        $tenant = $this->tenant();
        $this->request($tenant, 'PR-AWARD', 'for_canvass', ['request_for_quotation', 'abstract_of_bids_quotation']);

        $page = $this->actingAs($tenant[2])->get(route('procurement'))->assertOk();
        $page->assertSee('Award')->assertSee('2 of 8')->assertDontSee('For Canvass');
        $metrics = $page->viewData('procurementMetrics');
        $this->assertSame(0, $metrics['forCanvass']);
        $this->assertSame(0, $metrics['completed']);
    }

    public function test_a_request_with_every_document_but_not_marked_complete_is_ready_to_mark_complete(): void
    {
        $tenant = $this->tenant();
        $request = $this->request($tenant, 'PR-READY', 'for_canvass', array_keys(ProcurementWorkspaceService::REQUIRED_DOCUMENTS));

        $page = $this->actingAs($tenant[2])->get(route('procurement'))->assertOk();
        $page->assertSee('Ready to mark complete')->assertSee('PR-READY')->assertSee(route('procurement.status', $request), false);
        $this->assertSame(0, $page->viewData('procurementMetrics')['completed']);
    }

    public function test_only_a_request_marked_complete_with_every_document_counts_as_completed(): void
    {
        $tenant = $this->tenant();
        $this->request($tenant, 'PR-DONE', 'completed', array_keys(ProcurementWorkspaceService::REQUIRED_DOCUMENTS));
        $this->request($tenant, 'PR-EARLY', 'completed', ['request_for_quotation']);
        $this->request($tenant, 'PR-APPROVED', 'approved');

        $metrics = $this->metrics($tenant[2]);
        $this->assertSame(1, $metrics['completed']);
        $this->assertEquals(1500, $metrics['completedAmount']);
        // The early-completed request (only an RFQ) and the approved one are both still at the canvass stage.
        $this->assertSame(2, $metrics['forCanvass']);
        $this->assertSame(3, $metrics['total']);
    }

    public function test_the_finished_request_leaves_the_attention_list_and_pending_approval_is_counted(): void
    {
        $tenant = $this->tenant();
        $this->request($tenant, 'PR-DONE', 'completed', array_keys(ProcurementWorkspaceService::REQUIRED_DOCUMENTS));
        $this->request($tenant, 'PR-WAIT', 'pending_approval');

        $page = $this->actingAs($tenant[2])->get(route('procurement'))->assertOk();
        $this->assertSame(1, $page->viewData('procurementMetrics')['pending']);
        $this->assertSame(['PR-WAIT'], $page->viewData('attentionRequests')->pluck('request_number')->values()->all());
    }

    public function test_saving_the_last_document_completes_the_request_by_itself(): void
    {
        $tenant = $this->tenant();
        $types = array_keys(ProcurementWorkspaceService::REQUIRED_DOCUMENTS);
        $last = array_pop($types);
        $this->assertSame('requisition_issuance_slip', $last);
        $request = $this->request($tenant, 'PR-LAST', 'for_canvass', $types);

        $this->actingAs($tenant[2])->post(route('procurement.documents.store', $request), ['document_type' => $last, 'document_date' => '2026-10-11'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame('complete', app(ProcurementWorkspaceService::class)->summary($request->fresh()->load('documents'))['stage']);
        $this->assertSame(1, $this->metrics($tenant[2])['completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'procurement_request_completed', 'school_id' => $request->school_id]);
    }

    public function test_a_document_that_is_not_the_last_one_does_not_complete_the_request(): void
    {
        $tenant = $this->tenant();
        $request = $this->request($tenant, 'PR-MID', 'for_canvass', ['request_for_quotation']);

        $this->actingAs($tenant[2])->post(route('procurement.documents.preview', $request), ['document_type' => 'abstract_of_bids_quotation', 'document_date' => '2026-10-11']);
        $this->assertSame('for_canvass', $request->fresh()->status);
    }

    public function test_the_preview_never_completes_a_request(): void
    {
        $tenant = $this->tenant();
        $types = array_keys(ProcurementWorkspaceService::REQUIRED_DOCUMENTS);
        $last = array_pop($types);
        $request = $this->request($tenant, 'PR-PREVIEW', 'for_canvass', $types);

        $this->actingAs($tenant[2])->post(route('procurement.documents.preview', $request), ['document_type' => $last, 'document_date' => '2026-10-11'])->assertOk();
        $this->assertSame('for_canvass', $request->fresh()->status);
    }
}
