<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierFromAbstractTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $pr = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-'.strtoupper($slug), 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);

        return [$school, $user, $pr];
    }

    private function supplier(): array
    {
        return ['business_name' => 'Lubas Trading', 'tax_type' => 'vat', 'status' => 'active'];
    }

    public function test_the_abstract_form_links_to_supplier_management_and_opens_again_when_asked(): void
    {
        [, $user, $pr] = $this->tenant('abs-a');

        $page = $this->actingAs($user)->get(route('procurement.documents', $pr))->assertOk();
        $page->assertSee(e(route('suppliers.create', ['return_to' => 'abstract', 'return_request' => $pr->id])), false)->assertSee('Add supplier');
        $page->assertDontSee('data-auto-open', false);

        $this->get(route('procurement.documents', [$pr, 'open' => 'abstract_of_bids_quotation']))->assertOk()
            ->assertSee('data-auto-open="abstract_of_bids_quotation"', false);
    }

    public function test_the_supplier_page_offers_a_way_back_to_the_abstract_only_when_it_came_from_there(): void
    {
        [, $user, $pr] = $this->tenant('abs-b');
        $back = e(route('procurement.documents', [$pr, 'open' => 'abstract_of_bids_quotation']));

        $this->actingAs($user)->get(route('suppliers.create', ['return_to' => 'abstract', 'return_request' => $pr->id]))->assertOk()
            ->assertSee('Back to Abstract of Bids')->assertSee($back, false)
            ->assertSee('name="return_to" value="abstract"', false)->assertSee('name="return_request" value="'.$pr->id.'"', false);

        $this->get(route('suppliers.create'))->assertOk()->assertDontSee('Back to Abstract of Bids')->assertDontSee('name="return_to"', false);
    }

    public function test_saving_the_supplier_returns_to_the_abstract_with_the_supplier_in_the_list(): void
    {
        [$school, $user, $pr] = $this->tenant('abs-c');

        $this->actingAs($user)->post(route('suppliers.store'), $this->supplier() + ['return_to' => 'abstract', 'return_request' => $pr->id])
            ->assertRedirect(route('procurement.documents', [$pr, 'open' => 'abstract_of_bids_quotation']))
            ->assertSessionHas('success');

        $this->assertSame($school->id, Supplier::where('business_name', 'Lubas Trading')->value('school_id'));
        $this->get(route('procurement.documents', [$pr, 'open' => 'abstract_of_bids_quotation']))->assertOk()->assertSee('Lubas Trading');
    }

    public function test_without_the_marker_saving_goes_to_the_supplier_list_as_before(): void
    {
        [, $user] = $this->tenant('abs-d');

        $this->actingAs($user)->post(route('suppliers.store'), $this->supplier())->assertRedirect(route('suppliers'));
    }

    public function test_a_return_target_in_another_school_is_ignored(): void
    {
        [, $user] = $this->tenant('abs-e');
        [, , $foreign] = $this->tenant('abs-f');

        $this->actingAs($user)->get(route('suppliers.create', ['return_to' => 'abstract', 'return_request' => $foreign->id]))->assertOk()
            ->assertDontSee('Back to Abstract of Bids')->assertDontSee('PR-2026-ABS-F');
        $this->post(route('suppliers.store'), $this->supplier() + ['return_to' => 'abstract', 'return_request' => $foreign->id])->assertRedirect(route('suppliers'));
        $this->post(route('suppliers.store'), ['business_name' => 'Evil', 'tax_type' => 'vat', 'return_to' => 'https://evil.test', 'return_request' => 'x'])->assertRedirect(route('suppliers'));
    }
}
