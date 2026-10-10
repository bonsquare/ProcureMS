<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NtpDeliveryDaysTest extends TestCase
{
    use RefreshDatabase;

    private function setupRequest(): array
    {
        $organization = Organization::create(['name' => 'Ntp', 'slug' => 'ntp', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'NTP', 'name' => 'Ntp School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-NTP', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);
        $item = $pr->items()->create(['organization_id' => $organization->id, 'name' => 'RICE', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 1500, 'total' => 1500]);
        $document = fn (string $type, string $number, array $metadata = []) => ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => $type, 'document_number' => $number, 'document_date' => now()->toDateString(), 'status' => 'prepared', 'metadata' => $metadata ?: null]);
        $document('request_for_quotation', 'RFQ-2026-001');
        $document('abstract_of_bids_quotation', 'ABQ-2026-001', ['bidders' => [['name' => 'Lubas Trading', 'prices' => [(string) $item->id => 1500]]]]);
        $document('notice_to_award', 'NOA-2026-001');

        return [$organization, $user, $pr];
    }

    private function ntp(array $extra = []): array
    {
        return ['document_type' => 'notice_to_proceed', 'document_date' => '2026-10-10', 'template_variant' => 'mooe', 'supplier_or_recipient' => 'Lubas Trading', ...$extra];
    }

    public function test_the_mooe_notice_to_proceed_needs_the_number_of_days_typed_in(): void
    {
        [, $user, $pr] = $this->setupRequest();

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), $this->ntp())->assertSessionHasErrors('delivery_days');
        $this->post(route('procurement.documents.store', $pr), $this->ntp(['delivery_days' => '0']))->assertSessionHasErrors('delivery_days');
        $this->assertSame(0, $pr->documents()->where('document_type', 'notice_to_proceed')->count());
    }

    public function test_the_typed_days_are_saved_and_printed_on_the_notice_to_proceed(): void
    {
        [, $user, $pr] = $this->setupRequest();

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), $this->ntp(['delivery_days' => '45']))->assertSessionHasNoErrors();
        $ntp = $pr->documents()->where('document_type', 'notice_to_proceed')->firstOrFail();

        $this->assertSame(45, (int) $ntp->metadata['delivery_days']);
        $this->get(route('procurement.documents.print', [$pr, $ntp]))->assertOk()->assertSee('<u>45</u>', false);
    }

    public function test_the_scheduled_delivery_wording_needs_no_days(): void
    {
        [, $user, $pr] = $this->setupRequest();

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), $this->ntp(['template_variant' => 'sbfp']))->assertSessionHasNoErrors();
        $this->assertSame(1, $pr->documents()->where('document_type', 'notice_to_proceed')->count());
    }

    public function test_the_purchase_order_takes_the_days_from_the_notice_to_proceed(): void
    {
        [$organization, $user, $pr] = $this->setupRequest();
        ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'notice_to_proceed', 'document_number' => 'NTP-2026-001', 'document_date' => now()->toDateString(), 'status' => 'prepared', 'metadata' => ['delivery_days' => 45, 'template_variant' => 'mooe']]);

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), ['document_type' => 'purchase_order', 'document_date' => '2026-10-11', 'supplier_or_recipient' => 'Lubas Trading'])->assertSessionHasNoErrors();

        $this->assertSame(45, (int) $pr->documents()->where('document_type', 'purchase_order')->firstOrFail()->metadata['delivery_days']);
    }

    public function test_the_forms_ask_for_days_on_the_ntp_and_never_copy_from_a_purchase_order(): void
    {
        [, $user, $pr] = $this->setupRequest();

        $this->actingAs($user)->get(route('procurement.documents', $pr))->assertOk()
            ->assertSee('data-ntp-days', false)->assertSee('data-po-days', false)
            ->assertDontSee('Save a Purchase Order first')->assertDontSee('copied from the saved Purchase Order');
    }
}
