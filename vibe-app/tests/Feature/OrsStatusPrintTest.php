<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Section C (Status of Obligation) is not on every school's ORS template, so the ORS form has a tick box for it. */
class OrsStatusPrintTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'ors', 'slug' => 'ors', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ORS', 'name' => 'Ors School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$school, $user];
    }

    private function payload(School $school, array $extra = []): array
    {
        return $extra + ['school_id' => $school->id, 'source_of_fund' => 'MOOE', 'purpose' => 'Office supplies', 'amount' => '1000', 'payee' => 'Ors Supplier'];
    }

    public function test_a_new_ors_always_includes_section_c(): void
    {
        [$school, $user] = $this->tenant();

        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $this->assertTrue(LiquidationReport::firstOrFail()->ors_include_status);

        $this->post(route('liquidation.store'), $this->payload($school, ['ors_include_status' => '0']))->assertSessionHasNoErrors();
        $this->assertTrue(LiquidationReport::orderByDesc('id')->firstOrFail()->ors_include_status, 'Section C is part of every ORS, whatever the form says');
    }

    public function test_the_ors_preview_draws_the_official_form_from_the_entry_and_saves_nothing(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);

        $html = $this->post(route('liquidation.preview'), $this->payload($school, ['payee' => 'Preview Trading', 'amount' => '2500.75', 'purpose' => 'Preview purpose']))->assertOk()->getContent();

        $this->assertStringContainsString('OBLIGATION REQUEST AND STATUS', $html);
        $this->assertStringContainsString('Preview Trading', $html);
        $this->assertStringContainsString('2,500.75', $html);
        $this->assertSame(0, LiquidationReport::count(), 'a preview is rolled back');

        $partial = $this->post(route('liquidation.preview'), ['school_id' => $school->id])->assertOk()->getContent();
        $this->assertStringContainsString('OBLIGATION REQUEST AND STATUS', $partial, 'a form that is still empty is drawn blank, not refused');
        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_the_ors_preview_shows_the_blank_form_even_when_the_school_has_budget_lines_and_none_is_chosen(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'office' => $school->name, 'fiscal_year' => now()->year,
            'start_date' => now()->year.'-01-01', 'end_date' => now()->year.'-12-31', 'source_of_fund' => 'MOOE', 'uacs_code' => '5020301000', 'particulars' => 'Test line',
            'amount' => 1000, 'q1_amount' => 1000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-TEST-1',
        ]);

        $html = $this->post(route('liquidation.preview'), ['school_id' => $school->id])->assertOk()->getContent();

        $this->assertStringContainsString('OBLIGATION REQUEST AND STATUS', $html, 'the blank official form is drawn, not "Preview not ready yet"');
        $this->assertStringNotContainsString('Preview not ready', $html);
        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_the_ors_payee_field_lists_the_suppliers_of_supplier_management(): void
    {
        [$school, $user] = $this->tenant();
        Supplier::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'business_name' => 'Listed Trading Co.', 'business_address' => 'Poblacion, Magpet', 'tin' => '123-456-789-000', 'status' => 'active']);
        Supplier::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'business_name' => 'Inactive Trading Co.', 'business_address' => 'Nowhere', 'tin' => '000', 'status' => 'inactive']);

        $this->actingAs($user)->get(route('budget'))->assertOk()
            ->assertSee('<datalist id="ors-suppliers">', false)
            ->assertSee('value="Listed Trading Co." data-address="Poblacion, Magpet" data-tin="123-456-789-000"', false)
            ->assertDontSee('Inactive Trading Co.');
    }

    public function test_the_ors_has_a_date_that_comes_first_and_prints_on_the_form(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);

        $page = $this->get(route('budget'))->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'name="procurement_request_id"'), strpos($page, 'name="ors_date"'), 'the Date field comes before the Linked Procurement Request');

        $this->post(route('liquidation.store'), $this->payload($school, ['ors_date' => '2026-03-05']))->assertSessionHasNoErrors();
        $this->assertSame('2026-03-05', LiquidationReport::firstOrFail()->submitted_at->toDateString());
        $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->assertSee('March 05, 2026');
        $this->post(route('liquidation.preview'), $this->payload($school, ['ors_date' => '2026-04-09']))->assertOk()->assertSee('April 09, 2026');
    }

    public function test_a_purchase_request_shows_that_it_is_not_obligated_until_its_ors_is_created(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-ORS', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved', 'source_of_fund' => 'MOOE']);

        $this->get(route('procurement.show', $pr))->assertOk()->assertSee('obligated by the Purchase Order')->assertDontSee('Not obligated yet');
        ProcurementDocument::create(['organization_id' => $school->organization_id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-2026-001', 'document_date' => now(), 'supplier_or_recipient' => 'Ors Supplier', 'status' => 'prepared', 'metadata' => ['winning_bid_amount' => 1400]]);
        $this->get(route('procurement.show', $pr->fresh()))->assertOk()
            ->assertSee('Not obligated yet')
            ->assertSee('budget?pr='.$pr->id.'&amp;new=1', false);

        $this->post(route('liquidation.store'), $this->payload($school, ['procurement_request_id' => $pr->id, 'amount' => '1400']))->assertSessionHasNoErrors();

        $this->get(route('procurement.show', $pr))->assertOk()->assertSee('Obligated')->assertSee(LiquidationReport::firstOrFail()->ors_number)->assertDontSee('Not obligated yet');
    }

    public function test_the_ors_particulars_are_the_purpose_of_the_purchase_request_as_per_the_po_number(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-PUR', 'title' => '3rd Quarter MOOE for SY 2026', 'transaction_description' => 'Procurement of Office Supply', 'amount' => 1500, 'status' => 'approved', 'source_of_fund' => 'MOOE']);
        ProcurementDocument::create(['organization_id' => $school->organization_id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-2026-002', 'document_date' => now(), 'supplier_or_recipient' => 'Ors Supplier', 'status' => 'prepared']);

        $this->assertSame('Procurement of Office Supply for 3rd Quarter MOOE for SY 2026', $pr->fresh()->purposeText());
        $this->assertSame('Procurement of Office Supply for 3rd Quarter MOOE for SY 2026 as per PO No. 2026-002', $pr->fresh()->orsParticulars());
        $this->get(route('budget'))->assertOk()->assertSee('data-title="Procurement of Office Supply for 3rd Quarter MOOE for SY 2026 as per PO No. 2026-002"', false);
    }

    public function test_the_ors_made_from_a_purchase_order_has_the_date_of_the_po(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-DAT', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved', 'source_of_fund' => 'MOOE']);
        ProcurementDocument::create(['organization_id' => $school->organization_id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-2026-003', 'document_date' => '2026-03-12', 'supplier_or_recipient' => 'Ors Supplier', 'status' => 'prepared']);

        $this->get(route('budget'))->assertOk()->assertSee('data-po-date="2026-03-12"', false);

        $this->post(route('liquidation.store'), $this->payload($school, ['procurement_request_id' => $pr->id, 'ors_date' => '2026-09-30']))->assertSessionHasNoErrors();
        $this->assertSame('2026-03-12', LiquidationReport::firstOrFail()->submitted_at->toDateString(), 'the date of the PO is the date of the ORS, whatever the form sent');
        $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->assertSee('March 12, 2026');
    }

    public function test_the_budget_page_shows_the_stage_of_a_request_not_its_raw_status(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-STG', 'title' => 'Rice', 'amount' => 1500, 'status' => 'for_canvass', 'source_of_fund' => 'MOOE']);
        $this->get(route('budget'))->assertOk()->assertSee('Canvass');

        ProcurementDocument::create(['organization_id' => $school->organization_id, 'procurement_request_id' => $pr->id, 'created_by' => $user->id, 'document_type' => 'purchase_order', 'document_number' => 'PO-2026-004', 'document_date' => now(), 'supplier_or_recipient' => 'Ors Supplier', 'status' => 'prepared']);

        $this->get(route('budget'))->assertOk()->assertDontSee('For Canvass');
    }

    public function test_a_school_user_with_the_accounting_permissions_can_review_the_ors_and_one_without_cannot(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $ors = LiquidationReport::firstOrFail();

        $this->get(route('accounting'))->assertOk()->assertSee(route('accounting.review', $ors), false);
        $this->patch(route('accounting.review', $ors), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $ors->fresh()->status);
        $this->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertSee(route('accounting.dv.store', $ors), false);

        $viewer = User::factory()->create(['role' => 'auditor', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);
        $this->actingAs($viewer)->get(route('accounting', ['tab' => 'for_dv']))->assertOk()->assertDontSee(route('accounting.dv.store', $ors), false);
    }

    public function test_the_ors_form_has_no_tick_box_for_section_c(): void
    {
        [, $user] = $this->tenant();

        $this->actingAs($user)->get(route('budget'))->assertOk()
            ->assertDontSee('name="ors_include_status"', false)
            ->assertDontSee('Include Section C, Status of Obligation, on the printed ORS');
    }

    public function test_the_printed_ors_office_and_address_are_the_payees_and_the_header_values_are_bold_and_underlined(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school, ['payee' => 'Cotabatao Electric Cooperative', 'payee_office' => 'Kidapawan Branch Office', 'payee_address' => 'Poblacion, Kidapawan City']))->assertSessionHasNoErrors();

        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('Kidapawan Branch Office', $html);
        $this->assertStringContainsString('Poblacion, Kidapawan City', $html);
        $this->assertStringNotContainsString('>'.$school->name.'<', $html, 'the Office line is the payee office, not the school');
        $this->assertSame(3, preg_match_all('/font-weight="bold" text-decoration="underline"[^>]*>[^<]*<\/text>/', $html), 'the serial number, the date and the fund cluster are bold and underlined');
    }

    public function test_section_c_reference_number_is_the_ors_number_without_the_letters(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school, ['manually_encode_ors_number' => '1', 'ors_number' => 'ORS-2026-0123']))->assertSessionHasNoErrors();

        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="ors-status" x="214.7"[^>]*>2026-0123<\/text>/', $html, 'ORS/JEV/Check/ADA/TRA No. shows the number only');
        $this->assertMatchesRegularExpression('/class="ors-status" x="293"[^>]*>1,000.00<\/text>/', $html, 'Obligation (a)');
        $this->assertMatchesRegularExpression('/class="ors-status" x="366"[^>]*>1,000.00<\/text>/', $html, 'Payable (b) is the same amount as the Obligation');
    }

    public function test_the_uacs_code_is_written_on_the_ors_only_when_its_box_is_ticked_and_mfo_pap_is_blank(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'office' => $school->name, 'fiscal_year' => now()->year, 'program' => 'A PROGRAM NAME',
            'start_date' => now()->year.'-01-01', 'end_date' => now()->year.'-12-31', 'source_of_fund' => 'MOOE', 'uacs_code' => '5020301000', 'particulars' => 'Test line',
            'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-TEST-2',
        ]);

        $this->get(route('budget'))->assertOk()->assertSee('name="ors_show_uacs" value="1" checked', false)->assertSee('Show the UACS code on the ORS');

        $this->post(route('liquidation.store'), $this->payload($school, ['budget_allocation_id' => $line->id]))->assertSessionHasNoErrors();
        $ticked = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();
        $this->assertStringContainsString('5020301000', $ticked);
        $this->assertMatchesRegularExpression('/x="427\.1" y="263\.7" text-anchor="middle"[^>]*>5020301000<\/text>/', $ticked, 'the code is centered in its column (390.88 to 463.34)');
        $this->assertStringNotContainsString('A PROGRAM NAME', $ticked, 'MFO/PAP is left blank');

        $this->post(route('liquidation.store'), $this->payload($school, ['budget_allocation_id' => $line->id, 'ors_show_uacs' => '0']))->assertSessionHasNoErrors();
        $unticked = $this->get(route('liquidation.print', LiquidationReport::orderByDesc('id')->firstOrFail()))->assertOk()->getContent();
        $this->assertStringNotContainsString('5020301000', $unticked);
    }

    public function test_the_printed_ors_always_has_section_c(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school));

        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet" data-official-page', $html);
        $this->assertStringNotContainsString('class="sheet no-status"', $html);
        $this->assertStringContainsString('y2="883.92"', $html);
        $this->assertStringContainsString('STATUS OF OBLIGATION', $html);
    }

    public function test_the_payee_office_and_address_are_printed_bold(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school, ['payee_office' => 'Kidapawan Branch Office', 'payee_address' => 'Poblacion, Kidapawan City']))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        foreach (['Ors Supplier', 'Kidapawan Branch Office', 'Poblacion, Kidapawan City'] as $value) {
            $this->assertMatchesRegularExpression('/font-weight="bold">'.preg_quote($value, '/').'<\/text>/', $html);
        }
    }

    public function test_the_reference_amount_and_balance_headings_have_room(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('y1="703" x2="594.84" y2="703"', $html);
        foreach (['x="139" y="716.3"[^>]*>Reference', 'x="424" y="716.3"[^>]*>Amount', 'x="527" y="732.4"[^>]*>Balance'] as $pattern) {
            $this->assertMatchesRegularExpression('/'.$pattern.'/', $html);
        }
    }

    public function test_the_status_of_obligation_details_start_one_line_below_the_top_of_the_row(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('class="ors-status" x="84.7" y="801" width="87.3"', $html);
        $this->assertMatchesRegularExpression('/x="49\.2" y="810\.3"[^>]*>'.preg_quote(now()->format('M d, Y'), '/').'<\/text>/', $html);
        $this->assertSame(5, substr_count($html, 'y="810.3"'), 'the date and the four values share one top line');
        $this->assertMatchesRegularExpression('/x="84.7" y="801" width="87.3" height="80.5"><div[^>]*font:bold 9\.5px/', $html);
        foreach (['x="49\.2" y="810\.3"', 'x="214\.7" y="810\.3"', 'x="293" y="810\.3"'] as $position) {
            $this->assertMatchesRegularExpression('/'.$position.'[^>]*font-weight="bold">/', $html);
        }
    }

    public function test_the_signature_blocks_have_aligned_colons_and_the_date_of_the_ors(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        foreach ([602.08, 623.82, 641.93, 667.29] as $y) {
            $this->assertStringContainsString('x="84.5" y="'.$y.'"', $html);
            $this->assertStringContainsString('x="384.5" y="'.$y.'"', $html);
        }
        $date = preg_quote(now()->format('F d, Y'), '/');
        $this->assertMatchesRegularExpression('/x="199\.3" y="667\.29" text-anchor="middle"[^>]*>'.$date.'<\/text>/', $html);
        $this->assertMatchesRegularExpression('/x="492\.4" y="667\.29" text-anchor="middle"[^>]*>'.$date.'<\/text>/', $html);
    }

    public function test_the_certified_paragraphs_of_a_and_b_sit_a_little_higher(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'y="518.28"'), 'Certified: and the first line of A and of B');
        $this->assertSame(2, substr_count($html, 'y="532.05"'));
        $this->assertSame(2, substr_count($html, 'y="545.09"'));
        $this->assertStringNotContainsString('y="525.28"', $html);
    }

    public function test_the_reference_number_heading_is_centered_in_its_column_and_clear_of_the_lines(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/x="214\.65" y="751\.34" text-anchor="middle"[^>]*font-size="9\.4"[^>]*>ORS\/JEV\/Check\/<\/text>/', $html);
        $this->assertMatchesRegularExpression('/x="214\.65" y="764\.38" text-anchor="middle"[^>]*font-size="9\.4"[^>]*>ADA\/TRA No\.<\/text>/', $html);
    }

    public function test_the_fund_cluster_of_an_ors_defaults_to_101101_and_can_be_changed(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'office' => $school->name, 'fiscal_year' => now()->year, 'program' => 'A PROGRAM NAME',
            'start_date' => now()->year.'-01-01', 'end_date' => now()->year.'-12-31', 'source_of_fund' => 'MOOE', 'uacs_code' => '5020301000', 'particulars' => 'Test line',
            'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-TEST-3',
        ]);
        $this->get(route('budget'))->assertOk()->assertSee('name="ors_fund_cluster" maxlength="100" value="101101"', false);

        $this->post(route('liquidation.store'), $this->payload($school, ['budget_allocation_id' => $line->id]))->assertSessionHasNoErrors();
        $default = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/font-weight="bold" text-decoration="underline"[^>]*>101101<\/text>/', $default);

        $this->post(route('liquidation.store'), $this->payload($school, ['budget_allocation_id' => $line->id, 'ors_fund_cluster' => '101102']))->assertSessionHasNoErrors();
        $changed = $this->get(route('liquidation.print', LiquidationReport::orderByDesc('id')->firstOrFail()))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/font-weight="bold" text-decoration="underline"[^>]*>101102<\/text>/', $changed);
        $this->assertStringNotContainsString('>101101</text>', $changed);
    }

    public function test_the_create_ors_form_has_no_source_of_fund_field_and_the_fund_follows_the_budget_line(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);
        $line = BudgetAllocation::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'office' => $school->name, 'fiscal_year' => now()->year, 'program' => 'A PROGRAM NAME',
            'start_date' => now()->year.'-01-01', 'end_date' => now()->year.'-12-31', 'source_of_fund' => 'Canteen Fund', 'uacs_code' => '5020301000', 'particulars' => 'Test line',
            'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-TEST-4',
        ]);

        $page = $this->get(route('budget'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Source of Fund <span', $page);
        $this->assertStringContainsString('type="hidden" name="source_of_fund"', $page);
        foreach (['Document', 'Payee', 'Obligation'] as $heading) {
            $this->assertStringContainsString('tracking-wider text-primary">'.$heading.'</h3>', $page);
        }

        $this->post(route('liquidation.store'), $this->payload($school, ['budget_allocation_id' => $line->id, 'source_of_fund' => null]))->assertSessionHasNoErrors();
        $this->assertSame('Canteen Fund', LiquidationReport::firstOrFail()->source_of_fund);
    }
}
