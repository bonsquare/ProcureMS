<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An ORS that Accounting returned can be corrected by whoever made it, and goes back to Accounting for review. */
class ReturnedOrsEditTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: LiquidationReport, 1: User, 2: School} */
    private function returnedOrs(float $amount = 3000, ?int $procurementRequestId = null): array
    {
        $organization = Organization::create(['name' => 'ro', 'slug' => 'ro', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'RO', 'name' => 'Returned School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $line = BudgetAllocation::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'office' => $school->name, 'fiscal_year' => now()->year, 'program' => 'A PROGRAM',
            'start_date' => now()->year.'-01-01', 'end_date' => now()->year.'-12-31', 'source_of_fund' => 'MOOE', 'uacs_code' => '5020301000', 'particulars' => 'Test line',
            'amount' => 5000, 'q1_amount' => 5000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'created_by' => $user->id, 'budget_ref_no' => 'BA-RO-1',
        ]);
        $report = LiquidationReport::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'budget_allocation_id' => $line->id, 'procurement_request_id' => $procurementRequestId,
            'report_number' => 'LR-RO-1', 'ors_number' => 'ORS-RO-1', 'amount' => $amount, 'status' => 'returned', 'accounting_remarks' => 'Wrong supplier name',
            'source_of_fund' => 'MOOE', 'payee' => 'Wrong Supplier', 'purpose' => 'Old purpose', 'submitted_at' => now(),
        ]);

        return [$report, $user, $school];
    }

    private function form(array $overrides = []): array
    {
        return array_replace(['payee' => 'Right Supplier', 'payee_office' => 'Main Office', 'payee_address' => 'Kidapawan City', 'payee_tin' => '123-456', 'purpose' => 'Corrected purpose', 'amount' => '2500', 'notes' => 'Fixed', 'ors_fund_cluster' => '101102'], $overrides);
    }

    public function test_the_budget_page_offers_edit_with_the_accounting_remarks_only_for_a_returned_ors(): void
    {
        [$report, $user] = $this->returnedOrs();

        $this->actingAs($user)->get(route('budget'))->assertOk()
            ->assertSee('aria-label="Edit ORS ORS-RO-1"', false)->assertSee('flex items-center justify-end gap-1.5 whitespace-nowrap', false)->assertSee('edit</span></button>', false)->assertSee(route('liquidation.update', $report))->assertSee('Wrong supplier name');

        $report->update(['status' => 'approved']);
        $this->get(route('budget'))->assertOk()->assertDontSee(route('liquidation.update', $report));
    }

    public function test_the_creator_corrects_the_ors_and_it_goes_back_to_for_review(): void
    {
        [$report, $user] = $this->returnedOrs();

        $this->actingAs($user)->patch(route('liquidation.update', $report), $this->form())->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('for_review', $report->status);
        $this->assertSame('Right Supplier', $report->payee);
        $this->assertSame('Corrected purpose', $report->purpose);
        $this->assertSame('2500.00', $report->amount);
        $this->assertSame('101102', $report->ors_fund_cluster);
        $this->assertSame('Wrong supplier name', $report->accounting_remarks, 'the remarks stay as history');
    }

    public function test_an_ors_asked_for_documents_can_be_corrected_too(): void
    {
        [$report, $user] = $this->returnedOrs();
        $report->update(['status' => 'pending_documents']);

        $this->actingAs($user)->patch(route('liquidation.update', $report), $this->form())->assertSessionHasNoErrors();
        $this->assertSame('for_review', $report->fresh()->status);
    }

    public function test_a_bigger_amount_is_checked_again_against_the_budget_line(): void
    {
        [$report, $user] = $this->returnedOrs(3000);

        $this->actingAs($user)->patch(route('liquidation.update', $report), $this->form(['amount' => '6000']))->assertSessionHasErrors();
        $this->assertSame('returned', $report->fresh()->status);
        $this->assertSame('3000.00', $report->fresh()->amount);

        $this->patch(route('liquidation.update', $report), $this->form(['amount' => '4800']))->assertSessionHasNoErrors();
        $this->assertSame('4800.00', $report->fresh()->amount, 'the ORS does not count twice against its own budget line');
    }

    public function test_the_amount_of_an_ors_made_from_a_purchase_order_does_not_change(): void
    {
        [$report, $user, $school] = $this->returnedOrs(3000);
        $pr = ProcurementRequest::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-RO', 'title' => 'Supplies', 'amount' => 3000, 'status' => 'approved', 'source_of_fund' => 'MOOE']);
        $report->update(['procurement_request_id' => $pr->id]);

        $this->actingAs($user)->patch(route('liquidation.update', $report), $this->form(['amount' => '1000']))->assertSessionHasNoErrors();

        $this->assertSame('3000.00', $report->fresh()->amount);
        $this->assertSame('Corrected purpose', $report->fresh()->purpose);
    }

    public function test_only_a_returned_ors_without_a_dv_can_be_edited(): void
    {
        [$report, $user] = $this->returnedOrs();

        $report->update(['status' => 'approved']);
        $this->actingAs($user)->patch(route('liquidation.update', $report), $this->form())->assertStatus(422);

        $report->update(['status' => 'returned', 'dv_number' => 'DV-RO-1']);
        $this->patch(route('liquidation.update', $report), $this->form())->assertStatus(422);
    }

    public function test_someone_who_cannot_create_obligations_or_belongs_to_another_organization_cannot_edit(): void
    {
        [$report] = $this->returnedOrs();
        $other = Organization::create(['name' => 'other', 'slug' => 'other', 'status' => 'active']);
        $school = School::create(['organization_id' => $other->id, 'code' => 'OT', 'name' => 'Other School', 'status' => 'active']);
        $intruder = User::factory()->create(['role' => 'school_admin', 'organization_id' => $other->id, 'school_id' => $school->id]);

        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $report->organization_id, 'school_id' => $report->school_id]);
        Subscription::create(['organization_id' => $report->organization_id, 'school_id' => $report->school_id, 'user_id' => $viewer->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth()]);
        $this->actingAs($viewer)->patch(route('liquidation.update', $report), $this->form())->assertForbidden();

        $this->actingAs($intruder)->patch(route('liquidation.update', $report), $this->form())->assertNotFound();

        $this->assertSame('returned', $report->fresh()->status);
        $this->assertSame('Wrong Supplier', $report->fresh()->payee);
    }

    public function test_the_window_shows_the_official_ors_beside_the_form_and_previews_without_saving(): void
    {
        [$report, $user] = $this->returnedOrs();
        $this->actingAs($user)->get(route('budget'))->assertOk()
            ->assertSee('data-ors-edit data-split data-preview="'.route('liquidation.update.preview', $report).'"', false)->assertSee('Official ORS (updates as you edit)')
            ->assertSee('Edit Obligation Request and Status (ORS)')->assertSee('tracking-wider text-primary">Document</h3>', false)->assertSee('tracking-wider text-primary">Payee</h3>', false)->assertSee('tracking-wider text-primary">Obligation</h3>', false);

        $html = $this->post(route('liquidation.update.preview', $report), $this->form(['payee' => 'Preview Supplier', 'purpose' => 'Preview purpose']))->assertOk()->getContent();

        $this->assertStringContainsString('Preview Supplier', $html);
        $this->assertStringContainsString('Preview purpose', $html);
        $fresh = $report->fresh();
        $this->assertSame('returned', $fresh->status);
        $this->assertSame('Wrong Supplier', $fresh->payee);
        $this->assertSame('3000.00', $fresh->amount);
    }

    public function test_the_preview_is_still_drawn_when_something_typed_is_not_valid_yet(): void
    {
        [$report, $user] = $this->returnedOrs();

        $html = $this->actingAs($user)->post(route('liquidation.update.preview', $report), $this->form(['purpose' => '', 'payee' => 'Half typed supplier']))->assertOk()->getContent();

        $this->assertStringContainsString('Half typed supplier', $html);
        $this->assertSame('Wrong Supplier', $report->fresh()->payee);
    }

    public function test_the_preview_follows_the_same_rules_as_the_edit(): void
    {
        [$report, $user] = $this->returnedOrs();
        $report->update(['status' => 'approved']);
        $this->actingAs($user)->post(route('liquidation.update.preview', $report), $this->form())->assertStatus(422);
    }
}
