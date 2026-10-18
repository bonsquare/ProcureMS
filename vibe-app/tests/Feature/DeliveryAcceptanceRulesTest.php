<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-021 and T-022 (owner's decisions D5 and D6, 2026-10-17): an Inspection and Acceptance Report needs a typed received
 * quantity for every line and may be saved by an inspection officer; a DV for an ORS made from a Purchase Order waits
 * for that report.
 */
class DeliveryAcceptanceRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: School, 2: ProcurementRequest, 3: ProcurementRequestItem} */
    private function purchase(): array
    {
        $organization = Organization::create(['name' => 'Delivery Org', 'slug' => 'delivery-org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'DLV', 'name' => 'Delivery School', 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $request = ProcurementRequest::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $owner->id,
            'request_number' => 'PR-2026-D1', 'title' => 'Supplies', 'amount' => 2500, 'status' => 'approved',
        ]);
        $item = ProcurementRequestItem::create(['organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'name' => 'Bond paper', 'quantity' => 10, 'unit' => 'ream', 'unit_price' => 250, 'total' => 2500]);
        ProcurementDocument::create([
            'organization_id' => $organization->id, 'procurement_request_id' => $request->id, 'created_by' => $owner->id,
            'document_type' => 'purchase_order', 'document_number' => 'PO-2026-001', 'document_date' => now(), 'status' => 'prepared',
            'supplier_or_recipient' => 'Winning Trading', 'metadata' => ['source_of_fund' => 'MOOE'],
        ]);

        return [$organization, $school, $request, $item];
    }

    private function userOf(Organization $organization, School $school, string $role): User
    {
        return User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    private function iar(array $received): array
    {
        return ['document_type' => 'inspection_acceptance_report', 'document_date' => '2026-03-10', 'received_items' => $received];
    }

    public function test_an_iar_needs_a_typed_received_quantity_for_every_line(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $user = $this->userOf($organization, $school, 'school_admin');
        $this->actingAs($user);

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '']))->assertSessionHasErrors('received_items');
        $this->post(route('procurement.documents.store', $request), $this->iar([]))->assertSessionHasErrors('received_items');
        $this->assertSame(0, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->count());

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '11']))->assertSessionHasErrors('received_items');
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '0']))->assertSessionHasNoErrors();
        $this->assertSame(1, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->count());

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '10']))->assertSessionHasNoErrors();
        $this->assertEquals(10, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->firstOrFail()->metadata['received_items'][$item->id]);
    }

    public function test_an_inspection_officer_saves_the_iar_but_no_other_document(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $inspector = $this->userOf($organization, $school, 'inspector');
        $this->assertFalse($inspector->hasPermission('procurement.edit'));
        $this->assertTrue($inspector->hasPermission('inspection.edit'));

        $this->actingAs($inspector)->post(route('procurement.documents.store', $request), $this->iar([$item->id => '10']))->assertSessionHasNoErrors();
        $this->assertSame(1, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->count());

        $this->post(route('procurement.documents.store', $request), ['document_type' => 'request_for_quotation', 'document_date' => '2026-03-10'])->assertForbidden();
        $this->post(route('procurement.documents.preview', $request), ['document_type' => 'request_for_quotation', 'document_date' => '2026-03-10'])->assertForbidden();
        $this->assertSame(0, ProcurementDocument::where('document_type', 'request_for_quotation')->count());
    }

    public function test_roles_that_edit_procurement_keep_the_iar_and_a_viewer_cannot_save_it(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();

        $this->actingAs($this->userOf($organization, $school, 'procurement_officer'))
            ->post(route('procurement.documents.store', $request), $this->iar([$item->id => '5']))->assertSessionHasNoErrors();
        $this->assertSame(1, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->count());

        $this->actingAs($this->userOf($organization, $school, 'viewer'))
            ->post(route('procurement.documents.store', $request), $this->iar([$item->id => '10']))->assertForbidden();
        $this->assertEquals(5, ProcurementDocument::where('document_type', 'inspection_acceptance_report')->firstOrFail()->metadata['received_items'][$item->id]);
    }

    private function approvedOrs(Organization $organization, School $school, ?ProcurementRequest $request): LiquidationReport
    {
        return LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'procurement_request_id' => $request?->id, 'report_number' => 'LR-D-1', 'ors_number' => 'ORS-D-1',
            'amount' => 2500, 'status' => 'approved', 'payee' => 'Winning Trading', 'purpose' => 'Office supplies',
        ]);
    }

    /** @return array<string, mixed> */
    private function dvForm(): array
    {
        $accounts = collect(ChartOfAccount::standardAccounts());

        return [
            'dv_date' => now()->toDateString(), 'payee' => 'Winning Trading', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [
                ['account_code' => $accounts->firstWhere('category', 'MOOE')['code'], 'debit' => '2500', 'credit' => ''],
                ['account_code' => '1010404000', 'debit' => '', 'credit' => '2500'],
            ],
        ];
    }

    public function test_a_dv_for_an_ors_from_a_purchase_order_waits_for_the_iar(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $report = $this->approvedOrs($organization, $school, $request);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master);

        $this->post(route('accounting.dv.store', $report), $this->dvForm())->assertStatus(422);
        $this->post(route('accounting.dv.preview', $report), $this->dvForm())->assertStatus(422);
        $this->assertNull($report->fresh()->dv_number);

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '10']))->assertSessionHasNoErrors();

        $this->post(route('accounting.dv.store', $report), $this->dvForm())->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->dv_number);
    }

    public function test_an_ors_without_a_purchase_order_is_never_held(): void
    {
        [$organization, $school] = $this->purchase();
        $report = $this->approvedOrs($organization, $school, null);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->dvForm())->assertSessionHasNoErrors();

        $this->assertNotNull($report->fresh()->dv_number);
    }

    public function test_an_existing_dv_can_still_be_edited_without_an_iar(): void
    {
        [$organization, $school, $request] = $this->purchase();
        $report = $this->approvedOrs($organization, $school, $request);
        $report->forceFill(['dv_number' => 'DV-D-1', 'dv_date' => now()->toDateString(), 'dv_particulars' => 'Old', 'payment_mode' => 'MDS Check'])->save();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null, 'password' => bcrypt('edit-pass-123')]);

        $this->actingAs($master)->post(route('accounting.dv.store', $report), $this->dvForm() + ['dv_number' => 'DV-D-1', 'reason' => 'Correction', 'password' => 'edit-pass-123'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Office supplies', $report->fresh()->dv_particulars);
    }

    public function test_each_save_that_changes_the_received_total_adds_a_delivery_to_the_history(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $this->actingAs($this->userOf($organization, $school, 'school_admin'));
        $iar = fn () => ProcurementDocument::where('document_type', 'inspection_acceptance_report')->firstOrFail()->metadata;

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '4']))->assertSessionHasNoErrors();
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '4']))->assertSessionHasNoErrors();
        $this->post(route('procurement.documents.store', $request), ['document_date' => '2026-03-20'] + $this->iar([$item->id => '10']))->assertSessionHasNoErrors();
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '9']))->assertSessionHasNoErrors();

        $deliveries = $iar()['deliveries'];
        $this->assertCount(3, $deliveries, 'a save that changes nothing adds no delivery');
        $this->assertEquals([4, 6, -1], array_map(fn ($delivery) => $delivery['items'][$item->id], $deliveries));
        $this->assertEquals(9, $iar()['received_items'][$item->id]);
        $this->assertSame('2026-03-20', $deliveries[1]['date']);
    }

    public function test_rejected_quantity_is_recorded_stays_in_the_balance_and_is_printed_in_the_history(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $this->actingAs($this->userOf($organization, $school, 'school_admin'));
        $iar = fn () => ProcurementDocument::where('document_type', 'inspection_acceptance_report')->firstOrFail()->metadata;

        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '6']) + ['rejected_items' => [$item->id => '2']])->assertSessionHasNoErrors();
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '6']) + ['rejected_items' => [$item->id => '2']])->assertSessionHasNoErrors();
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '6']) + ['rejected_items' => [$item->id => '-1']])->assertSessionHasErrors('rejected_items.'.$item->id);

        $this->assertEquals(2, $iar()['rejected_items'][$item->id]);
        $this->assertCount(1, $iar()['deliveries'], 'the second identical save adds nothing');
        $this->assertEquals([$item->id => 2], array_map('floatval', $iar()['deliveries'][0]['rejected']));
        $this->assertEquals(6, $iar()['received_items'][$item->id], 'received_items holds what was accepted: the balance is 4');

        $this->get(route('procurement.delivery-reconciliation', $request))->assertOk()->assertSee('Delivery History')->assertSee('Bond paper +6')->assertSee('Bond paper +2');
    }

    public function test_the_dv_form_says_when_less_was_accepted_than_the_obligation(): void
    {
        [$organization, $school, $request, $item] = $this->purchase();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master);
        $this->post(route('procurement.documents.store', $request), $this->iar([$item->id => '4']))->assertSessionHasNoErrors();
        $report = $this->approvedOrs($organization, $school, $request);
        // Not yet with a DV: the note rides on the Create DV button.
        $this->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee('data-accepted-note="Accepted so far: ₱1,000.00 of the ₱2,500.00 obligated', false);

        $report->forceFill(['dv_number' => 'DV-D-9', 'dv_date' => now()->toDateString(), 'dv_particulars' => 'Supplies', 'payment_mode' => 'MDS Check'])->save();
        $page = $this->get(route('accounting', ['tab' => 'with_dv']))->assertOk();
        $page->assertSee('Accepted so far', false);
    }

    public function test_the_suggested_dv_number_is_the_next_one_of_the_ors_own_organization_even_for_a_master_user(): void
    {
        [$organization, $school] = $this->purchase();
        $year = now()->year;
        LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-D-7', 'ors_number' => 'ORS-D-7', 'amount' => 100, 'status' => 'approved',
            'payee' => 'Someone', 'purpose' => 'Earlier', 'dv_number' => "DV-{$year}-0007", 'dv_date' => now()->toDateString(), 'payment_mode' => 'MDS Check',
        ]);
        $this->approvedOrs($organization, $school, null);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee('data-next-dv="DV-'.$year.'-0008"', false);
    }

    public function test_the_liquidation_page_cannot_return_an_ors_that_is_paid_or_already_has_a_dv(): void
    {
        [$organization, $school] = $this->purchase();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master);
        $plain = $this->approvedOrs($organization, $school, null);
        $withDv = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-D-2', 'ors_number' => 'ORS-D-2', 'amount' => 100, 'status' => 'approved',
            'payee' => 'X', 'purpose' => 'Y', 'dv_number' => 'DV-D-2', 'dv_date' => now()->toDateString(), 'payment_mode' => 'MDS Check',
        ]);
        $paid = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'report_number' => 'LR-D-3', 'ors_number' => 'ORS-D-3', 'amount' => 100, 'status' => 'approved',
            'payee' => 'X', 'purpose' => 'Y', 'dv_number' => 'DV-D-3', 'dv_date' => now()->toDateString(), 'payment_mode' => 'MDS Check', 'paid_at' => now(),
        ]);

        $this->patch(route('liquidation.status', $paid), ['status' => 'returned'])->assertStatus(422);
        $this->patch(route('liquidation.status', $withDv), ['status' => 'pending_documents'])->assertStatus(422);
        $this->assertSame('approved', $paid->fresh()->status);
        $this->assertSame('approved', $withDv->fresh()->status);

        $this->patch(route('liquidation.status', $plain), ['status' => 'returned'])->assertSessionHasNoErrors();
        $this->assertSame('returned', $plain->fresh()->status);
    }
}
