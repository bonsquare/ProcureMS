<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SignatorySlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SobBackedBudgetLine;
use Tests\TestCase;

class DocumentSignatoriesTest extends TestCase
{
    use RefreshDatabase;
    use SobBackedBudgetLine;

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

    private function requestFor(School $school, User $user): ProcurementRequest
    {
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-SIG', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);
        $pr->items()->create(['organization_id' => $school->organization_id, 'name' => 'RICE', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 1500, 'total' => 1500]);

        return $pr;
    }

    private function documentFor(ProcurementRequest $pr, User $user, string $type, array $metadata = []): ProcurementDocument
    {
        return ProcurementDocument::create([
            'organization_id' => $pr->organization_id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => $type,
            'document_number' => strtoupper(substr($type, 0, 3)).'-2026-001', 'document_date' => '2026-10-11', 'status' => 'prepared', 'metadata' => $metadata,
        ]);
    }

    private function snapshot(SchoolStaff $member): array
    {
        return ['staff_id' => $member->id, 'name' => $member->name, 'position' => $member->position];
    }

    private function printed(ProcurementRequest $pr, ProcurementDocument $document): string
    {
        return strtolower($this->get(route('procurement.documents.print', [$pr, $document]))->assertOk()->getContent());
    }

    /** @return array<string, array{0: string, 1: string}> document type and the slot key to fill */
    public static function documentSlots(): array
    {
        return [
            'rfq' => ['request_for_quotation', 'bac_chair'],
            'abstract' => ['abstract_of_bids_quotation', 'bac_vice_chair'],
            'noa' => ['notice_to_award', 'school_head'],
            'ntp' => ['notice_to_proceed', 'school_head'],
            'po' => ['purchase_order', 'disbursing_officer'],
            'iar' => ['inspection_acceptance_report', 'inspection_officer'],
            'iars' => ['inventory_acknowledgement_receipt_supplies', 'school_head'],
            'ris' => ['requisition_issuance_slip', 'property_officer'],
            'ics' => ['inventory_custodian_slip', 'procurement_officer'],
            'par' => ['property_acknowledgement_receipt', 'approved_by'],
        ];
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

    #[DataProvider('documentSlots')]
    public function test_a_saved_choice_prints_in_the_slot_for_each_document_type(string $type, string $slot): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen', ['position' => 'Teacher III']);
        $pr = $this->requestFor($school, $user);
        $document = $this->documentFor($pr, $user, $type, ['signatories' => [$slot => $this->snapshot($pat)]]);
        // Some forms list every active staff member, so the name may only come from the saved choice.
        $pat->update(['is_active' => false]);
        $this->actingAs($user);

        $this->assertStringContainsString('pat chosen', $this->printed($pr, $document));
    }

    public function test_without_a_choice_the_default_still_prints(): void
    {
        [, $school, $user] = $this->tenant();
        $this->staff($school, 'Dee Default', ['document_role' => 'Disbursing Officer']);
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $document = $this->documentFor($pr, $user, 'purchase_order');
        $this->actingAs($user);

        $this->assertStringContainsString('dee default', $this->printed($pr, $document));

        $document->update(['metadata' => ['signatories' => ['disbursing_officer' => $this->snapshot($pat)]]]);
        $printed = $this->printed($pr, $document->fresh());

        $this->assertStringContainsString('pat chosen', $printed);
        $this->assertStringNotContainsString('dee default', $printed);
    }

    public function test_a_renamed_or_deactivated_signer_still_prints_from_the_snapshot(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $document = $this->documentFor($pr, $user, 'purchase_order', ['signatories' => ['disbursing_officer' => $this->snapshot($pat)]]);
        $pat->update(['name' => 'Renamed Person', 'is_active' => false]);
        $this->actingAs($user);

        $printed = $this->printed($pr, $document);

        $this->assertStringContainsString('pat chosen', $printed);
        $this->assertStringNotContainsString('renamed person', $printed);
    }

    public function test_saving_a_document_stores_the_chosen_signatories_in_metadata(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen', ['position' => 'Teacher III']);
        $pr = $this->requestFor($school, $user);

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), [
            'document_type' => 'request_for_quotation', 'document_date' => '2026-10-11', 'signatories' => ['bac_chair' => (string) $pat->id, 'bac_secretariat' => ''],
        ])->assertSessionHasNoErrors();

        $saved = $pr->documents()->where('document_type', 'request_for_quotation')->firstOrFail();
        $this->assertSame(['bac_chair' => $this->snapshot($pat)], $saved->metadata['signatories']);
    }

    public function test_resaving_without_the_signatories_field_keeps_earlier_choices_and_blank_values_clear_them(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $payload = ['document_type' => 'request_for_quotation', 'document_date' => '2026-10-11'];
        $this->actingAs($user)->post(route('procurement.documents.store', $pr), $payload + ['signatories' => ['bac_chair' => (string) $pat->id]])->assertSessionHasNoErrors();

        $this->post(route('procurement.documents.store', $pr), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['bac_chair' => $this->snapshot($pat)], $pr->documents()->first()->metadata['signatories']);

        $this->post(route('procurement.documents.store', $pr), $payload + ['signatories' => ['bac_chair' => '']])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('signatories', $pr->documents()->first()->metadata ?? []);
    }

    public function test_the_preview_shows_the_chosen_signer_and_saves_nothing(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);

        $response = $this->actingAs($user)->post(route('procurement.documents.preview', $pr), [
            'document_type' => 'request_for_quotation', 'document_date' => '2026-10-11', 'signatories' => ['bac_chair' => (string) $pat->id],
        ])->assertOk();

        $this->assertStringContainsString('pat chosen', strtolower($response->getContent()));
        $this->assertSame(0, ProcurementDocument::count());
    }

    public function test_only_the_chosen_bac_member_row_changes_on_the_abstract(): void
    {
        [, $school, $user] = $this->tenant();
        $this->staff($school, 'Mia First', ['bac_role' => 'BAC Member']);
        $this->staff($school, 'Ned Second', ['bac_role' => 'BAC Member']);
        $this->staff($school, 'Oli Third', ['bac_role' => 'BAC Member']);
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $document = $this->documentFor($pr, $user, 'abstract_of_bids_quotation', ['signatories' => ['bac_member_2' => $this->snapshot($pat)]]);
        $this->actingAs($user);

        $printed = $this->printed($pr, $document);

        $this->assertStringContainsString('mia first', $printed);
        $this->assertStringContainsString('oli third', $printed);
        $this->assertStringContainsString('pat chosen', $printed);
        $this->assertStringNotContainsString('ned second', $printed);
        $this->assertLessThan(strpos($printed, 'pat chosen'), strpos($printed, 'mia first'));
        $this->assertLessThan(strpos($printed, 'oli third'), strpos($printed, 'pat chosen'));
    }

    /** The HTML of one signatory select on the document page, so a test can look inside it. */
    private function selectOn(string $html, string $name): string
    {
        $this->assertSame(1, preg_match('/<select[^>]*name="'.preg_quote($name, '/').'"[^>]*>.*?<\/select>/s', $html, $match), "No select named {$name}");

        return $match[0];
    }

    public function test_the_document_page_shows_a_signatories_select_per_slot(): void
    {
        [, $school, $user] = $this->tenant();
        $pr = $this->requestFor($school, $user);

        $html = $this->actingAs($user)->get(route('procurement.documents', $pr))->assertOk()->getContent();

        foreach (['bac_chair', 'bac_secretariat', 'disbursing_officer', 'inspection_officer', 'bac_member_3', 'approved_by'] as $slot) {
            $this->assertStringContainsString('name="signatories['.$slot.']"', $html, $slot);
        }
    }

    public function test_role_holders_are_listed_before_other_staff_and_the_default_option_names_the_current_default(): void
    {
        [, $school, $user] = $this->tenant();
        $this->staff($school, 'Aaron Plain');
        $this->staff($school, 'Zoe Officer', ['document_role' => 'Disbursing Officer']);
        $pr = $this->requestFor($school, $user);

        $select = $this->selectOn($this->actingAs($user)->get(route('procurement.documents', $pr))->getContent(), 'signatories[disbursing_officer]');

        $this->assertStringContainsString('Default (Zoe Officer)', $select);
        $this->assertLessThan(strpos($select, 'Aaron Plain'), strrpos($select, 'Zoe Officer'));
    }

    public function test_a_saved_choice_is_selected_even_if_that_person_is_no_longer_active(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $this->documentFor($pr, $user, 'purchase_order', ['signatories' => ['disbursing_officer' => $this->snapshot($pat)]]);
        $pat->update(['is_active' => false]);

        $select = $this->selectOn($this->actingAs($user)->get(route('procurement.documents', $pr))->getContent(), 'signatories[disbursing_officer]');

        $this->assertMatchesRegularExpression('/<option[^>]*value="'.$pat->id.'"[^>]*selected[^>]*>[^<]*Pat Chosen/', $select);
    }

    public function test_a_chosen_inspection_officer_beats_the_typed_name_on_the_iar(): void
    {
        [, $school, $user] = $this->tenant();
        $pat = $this->staff($school, 'Pat Chosen');
        $pr = $this->requestFor($school, $user);
        $document = $this->documentFor($pr, $user, 'inspection_acceptance_report', ['inspection_officer_name' => 'Typed Name', 'signatories' => ['inspection_officer' => $this->snapshot($pat)]]);
        $this->actingAs($user);

        $printed = $this->printed($pr, $document);

        $this->assertStringContainsString('pat chosen', $printed);
        $this->assertStringNotContainsString('typed name', $printed);
    }

    private function prPayload(School $school, array $extra = []): array
    {
        return $extra + [
            'school_id' => $school->id, 'purpose' => 'Office supplies', 'entity_name' => 'DepEd', 'department_name' => $school->name,
            'request_date' => '2026-03-01', 'source_of_fund' => 'MOOE', 'transaction_description' => null, 'section' => null,
            'sai_number' => null, 'sai_date' => null, 'responsibility_center_code' => null, 'extra_blank_rows' => 0,
            'items' => [['name' => 'Bond paper', 'quantity' => 4, 'unit' => 'ream', 'unit_price' => 250]],
        ];
    }

    public function test_the_pr_prints_the_chosen_requested_by_and_approved_by(): void
    {
        [, $school, $user] = $this->tenant();
        $ana = $this->staff($school, 'Ana Requester', ['position' => 'Teacher I']);
        $head = $this->staff($school, 'Hal Approver', ['position' => 'Principal II']);
        $pr = $this->requestFor($school, $user);
        $pr->update(['signatories' => ['requested_by' => $this->snapshot($ana), 'approved_by' => $this->snapshot($head)]]);
        $ana->update(['is_active' => false]);
        $head->update(['is_active' => false]);

        $printed = strtolower($this->actingAs($user)->get(route('procurement.print', $pr))->assertOk()->getContent());

        $this->assertStringContainsString('ana requester', $printed);
        $this->assertStringContainsString('hal approver', $printed);
        $this->assertStringContainsString('principal ii', $printed);
    }

    public function test_a_new_pr_saves_chosen_signatories_and_an_edit_keeps_or_clears_them(): void
    {
        [, $school, $user] = $this->tenant();
        $ana = $this->staff($school, 'Ana Requester', ['position' => 'Teacher I']);

        $this->actingAs($user)->post(route('procurement.store'), $this->prPayload($school, ['signatories' => ['requested_by' => (string) $ana->id, 'approved_by' => '']]))->assertSessionHasNoErrors();
        $pr = ProcurementRequest::firstOrFail();
        $this->assertSame(['requested_by' => $this->snapshot($ana)], $pr->signatories);

        $this->put(route('procurement.update', $pr), $this->prPayload($school))->assertSessionHasNoErrors();
        $this->assertSame(['requested_by' => $this->snapshot($ana)], $pr->fresh()->signatories);

        $this->put(route('procurement.update', $pr), $this->prPayload($school, ['signatories' => ['requested_by' => '']]))->assertSessionHasNoErrors();
        $this->assertNull($pr->fresh()->signatories);
    }

    public function test_another_schools_staff_is_refused_on_the_pr(): void
    {
        [, $school, $user] = $this->tenant('one');
        [, $other] = $this->tenant('two');
        $stranger = $this->staff($other, 'Stranger');

        $this->actingAs($user)->post(route('procurement.store'), $this->prPayload($school, ['signatories' => ['approved_by' => (string) $stranger->id]]))
            ->assertSessionHasErrors('signatories.approved_by');

        $this->assertSame(0, ProcurementRequest::count());
    }

    public function test_the_pr_form_shows_the_signatories_selects_with_the_default_named(): void
    {
        [, $school, $user] = $this->tenant();
        $this->staff($school, 'Rita Requesting', ['procurement_role' => 'Requesting Officer']);
        $this->staff($school, 'Alma Approver', ['procurement_role' => 'Approver']);

        $html = $this->actingAs($user)->get(route('procurement.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="signatories[requested_by]"', $html);
        $this->assertStringContainsString('name="signatories[approved_by]"', $html);
        $this->assertStringContainsString('Default (Rita Requesting)', $html);
        $this->assertStringContainsString('Default (Alma Approver)', $html);
    }

    private function orsFor(School $school, array $extra = []): LiquidationReport
    {
        return LiquidationReport::create($extra + [
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'report_number' => 'LR-SIG-1', 'ors_number' => 'ORS-SIG-1', 'ors_kind' => 'other',
            'amount' => 1000, 'status' => 'approved', 'payee' => 'Sig Supplier', 'purpose' => 'Office supplies',
        ]);
    }

    private function orsPayload(School $school, array $extra = []): array
    {
        return $extra + ['school_id' => $school->id, 'budget_allocation_id' => $this->sobBackedLine($school)->id, 'source_of_fund' => 'MOOE', 'purpose' => 'Office supplies', 'amount' => '1000', 'payee' => 'Sig Supplier'];
    }

    public function test_the_ors_prints_the_chosen_requester_and_certifier(): void
    {
        [, $school, $user] = $this->tenant();
        $ana = $this->staff($school, 'Ana Head', ['position' => 'Principal II']);
        $ben = $this->staff($school, 'Ben Certifier', ['position' => 'Admin Aide']);
        $report = $this->orsFor($school, ['signatories' => ['ors' => ['requester' => $this->snapshot($ana), 'budget_officer' => $this->snapshot($ben)]]]);
        $ana->update(['is_active' => false]);
        $ben->update(['is_active' => false]);

        $printed = strtolower($this->actingAs($user)->get(route('liquidation.print', $report))->assertOk()->getContent());

        $this->assertStringContainsString('ana head', $printed);
        $this->assertStringContainsString('ben certifier', $printed);
        $this->assertStringContainsString('admin aide', $printed);
    }

    public function test_a_new_ors_saves_the_chosen_signatories_under_the_ors_key(): void
    {
        [, $school, $user] = $this->tenant();
        $ana = $this->staff($school, 'Ana Head', ['position' => 'Principal II']);

        $this->actingAs($user)->post(route('liquidation.store'), $this->orsPayload($school, ['signatories' => ['requester' => (string) $ana->id, 'budget_officer' => '']]))->assertSessionHasNoErrors();

        $this->assertSame(['ors' => ['requester' => $this->snapshot($ana)]], LiquidationReport::firstOrFail()->signatories);
    }

    public function test_another_schools_staff_is_refused_on_the_ors(): void
    {
        [, $school, $user] = $this->tenant('one');
        [, $other] = $this->tenant('two');
        $stranger = $this->staff($other, 'Stranger');

        $this->actingAs($user)->post(route('liquidation.store'), $this->orsPayload($school, ['signatories' => ['requester' => (string) $stranger->id]]))
            ->assertSessionHasErrors('signatories.requester');

        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_the_ors_form_shows_the_signatories_selects_with_the_default_named(): void
    {
        [, $school, $user] = $this->tenant();
        $this->staff($school, 'Sam Head', ['position' => 'School Head']);
        $this->staff($school, 'Dina Disburse', ['document_role' => 'Disbursing Officer']);

        $html = $this->actingAs($user)->get(route('budget'))->assertOk()->getContent();

        $this->assertStringContainsString('name="signatories[requester]"', $html);
        $this->assertStringContainsString('name="signatories[budget_officer]"', $html);
        $this->assertStringContainsString('Default (Sam Head)', $html);
        $this->assertStringContainsString('Default (Dina Disburse)', $html);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function dvPayload(array $extra = []): array
    {
        $expense = collect(ChartOfAccount::standardAccounts())->firstWhere('category', 'MOOE')['code'];

        return $extra + [
            'dv_date' => now()->toDateString(), 'payee' => 'Sig Supplier', 'dv_particulars' => 'Office supplies', 'payment_mode' => 'MDS Check',
            'journal' => [['account_code' => $expense, 'debit' => '1000', 'credit' => ''], ['account_code' => '1010404000', 'debit' => '', 'credit' => '1000']],
        ];
    }

    public function test_the_dv_prints_the_chosen_accountant_and_head(): void
    {
        [, $school] = $this->tenant();
        $ana = $this->staff($school, 'Ana Accountant', ['position' => 'Accountant II']);
        $hal = $this->staff($school, 'Hal Head', ['position' => 'Principal II']);
        $report = $this->orsFor($school, ['dv_number' => 'DV-SIG-1', 'dv_date' => now()->toDateString(), 'signatories' => ['dv' => ['accountant' => $this->snapshot($ana), 'head' => $this->snapshot($hal)]]]);
        $ana->update(['is_active' => false]);
        $hal->update(['is_active' => false]);

        $printed = strtolower($this->actingAs($this->master())->get(route('accounting.dv.print', $report))->assertOk()->getContent());

        $this->assertStringContainsString('ana accountant', $printed);
        $this->assertStringContainsString('hal head', $printed);
    }

    public function test_creating_a_dv_saves_the_chosen_signatories_and_keeps_the_ors_ones(): void
    {
        [, $school] = $this->tenant();
        $ana = $this->staff($school, 'Ana Accountant');
        $ben = $this->staff($school, 'Ben Requester');
        $report = $this->orsFor($school, ['signatories' => ['ors' => ['requester' => $this->snapshot($ben)]]]);

        $this->actingAs($this->master())->post(route('accounting.dv.store', $report), $this->dvPayload(['signatories' => ['accountant' => (string) $ana->id, 'head' => '']]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['ors' => ['requester' => $this->snapshot($ben)], 'dv' => ['accountant' => $this->snapshot($ana)]],
            $report->fresh()->signatories,
        );
    }

    public function test_the_dv_options_request_can_change_the_signatories_without_touching_the_appropriation_option(): void
    {
        [, $school] = $this->tenant();
        $ana = $this->staff($school, 'Ana Accountant');
        $report = $this->orsFor($school, ['dv_number' => 'DV-SIG-1', 'dv_include_appropriation' => true, 'signatories' => ['ors' => ['requester' => ['staff_id' => 1, 'name' => 'Keep Me', 'position' => null]]]]);

        $this->actingAs($this->master())->patch(route('accounting.dv.options', $report), ['signatories' => ['accountant' => (string) $ana->id]])->assertSessionHasNoErrors();

        $fresh = $report->fresh();
        $this->assertSame($this->snapshot($ana), $fresh->signatories['dv']['accountant']);
        $this->assertSame('Keep Me', $fresh->signatories['ors']['requester']['name']);
        $this->assertTrue($fresh->dv_include_appropriation);

        $this->patch(route('accounting.dv.options', $report), ['signatories' => ['accountant' => '']])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('dv', $report->fresh()->signatories);
    }

    public function test_another_schools_staff_is_refused_on_the_dv(): void
    {
        [, $school] = $this->tenant('one');
        [, $other] = $this->tenant('two');
        $stranger = $this->staff($other, 'Stranger');
        $report = $this->orsFor($school);

        $this->actingAs($this->master())->post(route('accounting.dv.store', $report), $this->dvPayload(['signatories' => ['head' => (string) $stranger->id]]))
            ->assertSessionHasErrors('signatories.head');
        $this->assertNull($report->fresh()->dv_number);

        $report->update(['dv_number' => 'DV-SIG-1']);
        $this->patch(route('accounting.dv.options', $report), ['signatories' => ['head' => (string) $stranger->id]])->assertSessionHasErrors('signatories.head');
    }

    public function test_the_accounting_page_shows_the_dv_signatories_selects(): void
    {
        [, $school] = $this->tenant();
        $this->staff($school, 'Acc Default', ['document_role' => 'Accountant']);
        $this->orsFor($school);
        $this->orsFor($school, ['report_number' => 'LR-SIG-2', 'ors_number' => 'ORS-SIG-2', 'dv_number' => 'DV-SIG-2', 'dv_date' => now()->toDateString()]);

        $html = $this->actingAs($this->master())->get(route('accounting', ['tab' => 'all']))->assertOk()->getContent();

        $this->assertStringContainsString('name="signatories[accountant]"', $html);
        $this->assertStringContainsString('name="signatories[head]"', $html);
        $this->assertStringContainsString('Default (Acc Default)', $html);
    }

    public function test_a_signer_from_another_school_is_refused_on_save(): void
    {
        [, $school, $user] = $this->tenant('one');
        [, $other] = $this->tenant('two');
        $stranger = $this->staff($other, 'Stranger');
        $pr = $this->requestFor($school, $user);

        $this->actingAs($user)->post(route('procurement.documents.store', $pr), [
            'document_type' => 'request_for_quotation', 'document_date' => '2026-10-11', 'signatories' => ['bac_chair' => (string) $stranger->id],
        ])->assertSessionHasErrors('signatories.bac_chair');

        $this->assertSame(0, ProcurementDocument::count());
    }
}
