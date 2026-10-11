<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SignatorySlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentSignatoriesTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'sig'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    private function staff(School $school, string $name, array $extra = []): SchoolStaff
    {
        return SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => $name] + $extra);
    }

    public function test_every_document_has_the_slots_the_spec_lists(): void
    {
        $expected = [
            'request_for_quotation' => ['bac_chair', 'bac_secretariat'],
            'abstract_of_bids_quotation' => ['bac_chair', 'bac_vice_chair', 'bac_member_1', 'bac_member_2', 'bac_member_3'],
            'notice_to_award' => ['school_head'],
            'notice_to_proceed' => ['school_head'],
            'purchase_order' => ['school_head', 'disbursing_officer'],
            'inspection_acceptance_report' => ['inspection_officer', 'property_officer'],
            'inventory_acknowledgement_receipt_supplies' => ['school_head'],
            'requisition_issuance_slip' => ['requesting_officer', 'school_head', 'property_officer'],
            'inventory_custodian_slip' => ['property_officer', 'procurement_officer', 'requesting_officer'],
            'property_acknowledgement_receipt' => ['prepared_by', 'approved_by'],
            'purchase_request' => ['requested_by', 'approved_by'],
            'obligation_request' => ['requester', 'budget_officer'],
            'disbursement_voucher' => ['accountant', 'head'],
        ];

        foreach ($expected as $document => $keys) {
            $this->assertSame($keys, array_column(SignatorySlots::slots($document), 'key'), $document);
        }
    }

    public function test_unknown_document_has_no_slots(): void
    {
        $this->assertSame([], SignatorySlots::slots('not_a_document'));
    }

    public function test_candidates_list_role_holders_first_then_other_active_staff(): void
    {
        [, $school] = $this->tenant();
        $this->staff($school, 'Zed Plain');
        $this->staff($school, 'Amy Plain');
        $this->staff($school, 'Walt Chair', ['bac_role' => 'BAC Chairperson']);
        $this->staff($school, 'Gone Inactive', ['bac_role' => 'BAC Chairperson', 'is_active' => false]);
        $slot = collect(SignatorySlots::slots('request_for_quotation'))->firstWhere('key', 'bac_chair');

        $names = SignatorySlots::candidates(SchoolStaff::where('school_id', $school->id)->get(), $slot)->pluck('name')->all();

        $this->assertSame(['Walt Chair', 'Amy Plain', 'Zed Plain'], $names);
    }

    public function test_validated_returns_snapshots_for_chosen_slots(): void
    {
        [, $school] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen', ['position' => 'Teacher III']);

        $snapshots = SignatorySlots::validated('purchase_order', ['school_head' => (string) $pat->id], $school->id);

        $this->assertSame(['school_head' => ['staff_id' => $pat->id, 'name' => 'Pat Chosen', 'position' => 'Teacher III']], $snapshots);
        $staff = SignatorySlots::toStaff($snapshots['school_head']);
        $this->assertSame('Pat Chosen', $staff->name);
        $this->assertSame('Teacher III', $staff->position);
    }

    public function test_blank_and_unknown_slots_are_ignored(): void
    {
        [, $school] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');

        $snapshots = SignatorySlots::validated('purchase_order', ['school_head' => '', 'disbursing_officer' => null, 'made_up_slot' => (string) $pat->id], $school->id);

        $this->assertSame([], $snapshots);
    }

    public function test_a_string_or_nested_array_input_is_ignored_without_error(): void
    {
        [, $school] = $this->tenant();

        $this->assertSame([], SignatorySlots::validated('purchase_order', 'x', $school->id));
        $this->assertSame([], SignatorySlots::validated('purchase_order', null, $school->id));
        $this->assertSame([], SignatorySlots::validated('purchase_order', ['school_head' => ['a']], $school->id));
    }

    public function test_another_schools_staff_is_refused(): void
    {
        [, $school] = $this->tenant('one');
        [, $other] = $this->tenant('two');
        $stranger = $this->staff($other, 'Stranger');

        try {
            SignatorySlots::validated('purchase_order', ['school_head' => (string) $stranger->id], $school->id);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('signatories.school_head', $exception->errors());
        }
    }

    public function test_an_inactive_or_unknown_staff_id_is_refused(): void
    {
        [, $school] = $this->tenant();
        $inactive = $this->staff($school, 'Gone', ['is_active' => false]);

        foreach ([(string) $inactive->id, '999999', 'abc'] as $value) {
            try {
                SignatorySlots::validated('purchase_order', ['disbursing_officer' => $value], $school->id);
                $this->fail("Expected a ValidationException for {$value}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('signatories.disbursing_officer', $exception->errors());
            }
        }
    }
}
