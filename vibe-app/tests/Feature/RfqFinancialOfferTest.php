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

/** The Financial Offer of the RFQ has the school's standard Terms of Payment sentence. */
class RfqFinancialOfferTest extends TestCase
{
    use RefreshDatabase;

    private function printedRfq(array $metadata = []): string
    {
        $organization = Organization::create(['name' => 'Rfq', 'slug' => 'rfq', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'RFQ', 'name' => 'Lubas Elementary School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-RFQ', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);
        $pr->items()->create(['organization_id' => $organization->id, 'name' => 'RICE', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 1500, 'total' => 1500]);
        $rfq = ProcurementDocument::create(['organization_id' => $organization->id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'request_for_quotation', 'document_number' => 'RFQ-2026-001', 'document_date' => now()->toDateString(), 'status' => 'prepared', 'metadata' => $metadata ?: null]);

        return $this->actingAs($user)->get(route('procurement.documents.print', [$pr, $rfq]))->assertOk()->getContent();
    }

    public function test_the_terms_of_payment_sentence_names_the_school_and_the_typed_terms(): void
    {
        $html = $this->printedRfq(['terms_of_payment' => '15 days']);

        $this->assertStringContainsString('FINANCIAL OFFER:', $html);
        $this->assertStringContainsString('<h2>Terms of Payment:</h2>', $html);
        $this->assertStringContainsString('<strong><em>LUBAS ELEMENTARY SCHOOL</em></strong> shall pay the supplier the actual amount of net of taxes in a send bill arrangement. Payment shall be made within <strong>15 days</strong> and after the submission of billing statement and completion of the deliverables and issuance of Certificate of Inspection and Acceptance.', $html);
    }

    public function test_the_typed_delivery_term_stands_under_the_title_of_the_quotation_page(): void
    {
        $html = $this->printedRfq(['rfq_delivery_term' => '15 calendar days upon receipt of Notice to Proceed', 'terms_of_payment' => '15 days']);

        $this->assertStringContainsString('<p class="center">Delivery: 15 calendar days upon receipt of Notice to Proceed</p>', $html);
        $this->assertStringContainsString('Payment shall be made within <strong>15 days</strong> and after', $html, 'the payment term is its own field');
    }

    public function test_without_typed_terms_the_delivery_and_payment_terms_use_their_defaults(): void
    {
        $html = $this->printedRfq(['delivery_term' => 'Pick-Up', 'payment_term' => '30 days']);
        $this->assertStringNotContainsString('Delivery: Pick-Up', $html, 'the Pick-Up of the other documents is a different field');

        $this->assertStringContainsString('<p class="center">Delivery: within thirty (30) calendar days from receipt of the Notice to Proceed (NTP)</p>', $html);
        $this->assertStringContainsString('Payment shall be made within <strong>30 days after the delivery</strong> and after', $html);
    }

    public function test_the_document_form_has_a_delivery_term_and_the_terms_are_saved(): void
    {
        $organization = Organization::create(['name' => 'Rfq', 'slug' => 'rfq', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'RFQ', 'name' => 'Lubas Elementary School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-RFQ', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);
        $pr->items()->create(['organization_id' => $organization->id, 'name' => 'RICE', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 1500, 'total' => 1500]);

        $this->actingAs($user)->get(route('procurement.documents', $pr))->assertOk()->assertSee('name="terms_of_payment" data-default="30 days after the delivery"', false)->assertSee('name="rfq_delivery_term" data-default="within thirty (30) calendar days from receipt of the Notice to Proceed (NTP)"', false);
        $this->post(route('procurement.documents.store', $pr), ['document_type' => 'request_for_quotation', 'document_date' => now()->toDateString(), 'supplier_or_recipient' => 'Lubas Trading', 'rfq_delivery_term' => '20 days', 'terms_of_payment' => '10 days'])->assertSessionHasNoErrors();

        $rfq = $pr->documents()->where('document_type', 'request_for_quotation')->firstOrFail();
        $this->assertSame('20 days', $rfq->metadata['rfq_delivery_term']);
        $this->assertSame('10 days', $rfq->metadata['terms_of_payment']);
    }
}
