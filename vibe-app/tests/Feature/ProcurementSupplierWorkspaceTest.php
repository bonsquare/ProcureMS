<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementSupplierWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_workspace_searches_filters_and_paginates(): void
    {
        $o = Organization::create(['name' => 'Supplier Org', 'slug' => 'supplier-workspace', 'status' => 'active']);
        $s = School::create(['organization_id' => $o->id, 'code' => 'SUP', 'name' => 'Supplier School', 'status' => 'active']);
        $u = User::factory()->create(['organization_id' => $o->id, 'school_id' => $s->id, 'role' => 'school_admin']);
        foreach (range(1, 21) as $i) {
            Supplier::create(['organization_id' => $o->id, 'school_id' => $s->id, 'business_name' => sprintf('Vendor %02d', $i), 'tax_type' => 'vat', 'tax_rate' => 12, 'status' => $i === 21 ? 'inactive' : 'active']);
        } $first = Supplier::orderBy('business_name')->first();
        $this->actingAs($u)->get(route('suppliers'))->assertOk()->assertSee('Supplier directory')->assertSee('civic-mobile-cards', false)->assertSee('Edit supplier')->assertSee(route('suppliers.edit', $first), false)->assertViewHas('suppliers', fn ($v) => $v->count() === 20 && $v->total() === 21);
        $this->actingAs($u)->get(route('suppliers', ['search' => 'Vendor 21', 'status' => 'inactive']))->assertOk()->assertSee('Vendor 21')->assertDontSee('Vendor 20');
    }

    public function test_full_supplier_profile_can_be_created_and_edited(): void
    {
        $o = Organization::create(['name' => 'Profile Org', 'slug' => 'supplier-profile', 'status' => 'active']);
        $s = School::create(['organization_id' => $o->id, 'code' => 'PRO', 'name' => 'Profile School', 'status' => 'active']);
        Subscription::create(['organization_id' => $o->id, 'school_id' => $s->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $u = User::factory()->create(['organization_id' => $o->id, 'school_id' => $s->id, 'role' => 'school_admin']);

        $this->actingAs($u)->get(route('suppliers.create'))->assertOk()->assertSee('Company Owner Details')->assertSee('Payment details')->assertSee('PhilGEPS registration no.');

        $this->post(route('suppliers.store'), [
            'business_name' => 'Rizal Trading', 'has_company_owner' => 1, 'owner_salutation' => 'Engr.', 'owner_given_name' => 'Juan', 'owner_middle_initial' => 'D', 'owner_last_name' => 'Cruz',
            'address_street' => '12 Rizal St.', 'address_barangay' => 'Poblacion', 'address_city' => 'Kidapawan City', 'address_province' => 'Cotabato', 'address_zip' => '9400',
            'phone' => '09171234567', 'email' => 'rizal@example.com', 'tax_type' => 'non_vat', 'tin' => '123-456-789-000', 'philgeps_no' => 'PG-1', 'business_permit_no' => 'BP-9',
            'payment_method' => 'Bank transfer', 'bank_name' => 'Landbank', 'bank_account_name' => 'Rizal Trading', 'bank_account_number' => '0011223344', 'dti_registration_no' => 'DTI-77',
        ])->assertRedirect(route('suppliers'));

        $supplier = Supplier::where('business_name', 'Rizal Trading')->firstOrFail();
        $this->assertSame('12 Rizal St., Poblacion, Kidapawan City, Cotabato, 9400', $supplier->business_address);
        $this->assertSame('Engr.', $supplier->owner_salutation);
        $this->assertSame('Landbank', $supplier->bank_name);

        $this->get(route('suppliers.edit', $supplier))->assertOk()->assertSee('Rizal Trading')->assertSee('0011223344');
        $this->put(route('suppliers.update', $supplier), ['business_name' => 'Rizal Trading', 'has_company_owner' => 0, 'tax_type' => 'vat', 'tax_rate' => 12, 'addressee' => 'The Manager'])->assertRedirect(route('suppliers'));
        $this->assertNull($supplier->fresh()->owner_given_name);
        $this->assertSame('The Manager', $supplier->fresh()->addressee);
    }
}
