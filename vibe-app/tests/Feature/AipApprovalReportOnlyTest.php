<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\BudgetAllocation;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An AIP is for reports and for starting a PPMP: approving it never touches the Budget. */
class AipApprovalReportOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function aip(string $status = 'draft', bool $withActivity = true, float $amount = 5000): array
    {
        $key = uniqid('ro');
        $organization = Organization::create(['name' => $key, 'slug' => $key, 'status' => 'active', 'fiscal_year' => 2026]);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($key), 'name' => 'Report School', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026, 'entity' => 'school', 'status' => $status]);
        $kra = AipKra::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'pillar' => 'Access', 'kra' => 'KRA 3', 'program' => 'Program One']);
        if ($withActivity) {
            // Funded, but with no source of fund and no account code: this is what used to block approval.
            AipActivity::create(['organization_id' => $organization->id, 'aip_id' => $aip->id, 'aip_kra_id' => $kra->id, 'activity' => 'Listing of pupils', 'physical_target' => 1, 'q1_amount' => $amount, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0]);
        }

        return [$aip, $master, $school, $organization];
    }

    public function test_an_aip_is_approved_without_a_source_of_fund_or_an_account_code(): void
    {
        [$aip, $master] = $this->aip();

        $this->actingAs($master)->post(route('aip.approve', $aip))->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $aip->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_approving_creates_no_budget_allotment_and_the_message_does_not_mention_one(): void
    {
        [$aip, $master] = $this->aip();

        $response = $this->actingAs($master)->post(route('aip.approve', $aip));

        $this->assertSame(0, BudgetAllocation::withoutGlobalScopes()->count());
        $this->assertStringNotContainsString('llotment', (string) session('success'));
        $this->assertStringNotContainsString('source of fund and an account code', (string) collect(session('errors')?->all())->implode(' '));
        $response->assertRedirect();
    }

    public function test_existing_allotments_from_earlier_approvals_are_left_alone(): void
    {
        [$aip, $master, $school, $organization] = $this->aip('revised');
        $line = BudgetAllocation::create([
            'aip_id' => $aip->id, 'school_id' => $school->id, 'organization_id' => $organization->id, 'office' => 'Report School', 'fiscal_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'source_of_fund' => 'MOOE', 'program' => 'Program One', 'uacs_code' => '5-02-99-990', 'particulars' => 'Old line', 'amount' => 777, 'q1_amount' => 777, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0,
            'budget_ref_no' => 'BA-2026-0001', 'created_by' => $master->id,
        ]);

        $this->actingAs($master)->post(route('aip.approve', $aip))->assertSessionHasNoErrors();

        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
        $this->assertEquals(777, $line->fresh()->amount);
        $this->assertSame('Old line', $line->fresh()->particulars);
    }

    public function test_an_aip_with_no_activity_cannot_be_approved(): void
    {
        [$aip, $master] = $this->aip(withActivity: false);

        $this->actingAs($master)->post(route('aip.approve', $aip))->assertSessionHasErrors('aip');
        $this->assertSame('draft', $aip->fresh()->status);
    }

    public function test_an_activity_without_a_financial_amount_is_enough_to_approve(): void
    {
        [$aip, $master] = $this->aip(amount: 0);

        $this->actingAs($master)->post(route('aip.approve', $aip))->assertSessionHasNoErrors();
        $this->assertSame('approved', $aip->fresh()->status);
    }

    public function test_an_already_approved_aip_is_not_approved_again(): void
    {
        [$aip, $master] = $this->aip('approved');
        $approvedAt = now()->subDay();
        $aip->forceFill(['approved_at' => $approvedAt])->save();

        $this->actingAs($master)->post(route('aip.approve', $aip))->assertSessionHasNoErrors();

        $this->assertSame($approvedAt->timestamp, $aip->fresh()->approved_at->timestamp);
    }

    public function test_the_aip_page_offers_approve_without_any_budget_wording(): void
    {
        [$aip, $master] = $this->aip();

        $page = $this->actingAs($master)->get(route('aip.show', $aip))->assertOk();
        $page->assertSee('Approve AIP')->assertDontSee('Create Allotments')->assertDontSee('Update Allotments')->assertDontSee('become budget allotments');
    }

    public function test_the_planning_and_aip_pages_no_longer_say_approval_creates_allotments(): void
    {
        [$aip, $master, $school] = $this->aip();

        $this->actingAs($master)->followingRedirects()->get(route('aip'))->assertOk()->assertDontSee('creates the budget allotments')->assertDontSee('creates the budget');
        $this->get(route('planning', ['school_id' => $school->id, 'year' => 2026]))->assertOk()->assertDontSee('creates the budget allotments');
    }
}
