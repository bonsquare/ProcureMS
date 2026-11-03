<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The budget item form, its save, and the balance lookup the forms use. */
class BudgetAllocationRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $fx, array $overrides = []): array
    {
        return $overrides + [
            'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'source_of_fund' => 'MOOE', 'office' => 'Office of the Principal',
            'chart_of_account_id' => $fx['account']->id, 'amount' => '1000', 'distribution' => 'equal', 'program' => '',
        ];
    }

    private function viewerOf(array $fx): User
    {
        return User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);
    }

    public function test_an_item_is_saved_with_the_amount_split_into_four_quarters(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->get(route('budget.allocation.create'))->assertOk();
        $this->post(route('budget.allocation.store'), $this->item($fx, ['amount' => '1000.01']))->assertSessionHasNoErrors()
            ->assertRedirect(route('budget.allocation', ['year' => 2026, 'school_id' => $fx['school']->id]));

        $item = BudgetAllocation::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals([250.0, 250.0, 250.0, 250.01], [(float) $item->q1_amount, (float) $item->q2_amount, (float) $item->q3_amount, (float) $item->q4_amount]);
        $this->assertNotEmpty($item->budget_ref_no);
        $this->assertSame($fx['user']->id, $item->created_by);
    }

    public function test_manual_quarters_must_add_up_to_the_annual_amount(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('budget.allocation.store'), $this->item($fx, ['distribution' => 'manual', 'q1_amount' => '400', 'q2_amount' => '400', 'q3_amount' => '100', 'q4_amount' => '0']))->assertSessionHasErrors('q1_amount');
        $this->post(route('budget.allocation.store'), $this->item($fx, ['distribution' => 'manual', 'q1_amount' => '400', 'q2_amount' => '300', 'q3_amount' => '200', 'q4_amount' => '100']))->assertSessionHasNoErrors();
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_an_item_needs_an_amount_a_fund_an_office_and_an_account_of_the_own_organization(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('budget.allocation.store'), $this->item($fx, ['amount' => '0']))->assertSessionHasErrors('amount');
        $this->post(route('budget.allocation.store'), $this->item($fx, ['source_of_fund' => '', 'office' => '']))->assertSessionHasErrors(['source_of_fund', 'office']);
        $this->post(route('budget.allocation.store'), $this->item($fx, ['chart_of_account_id' => $other['account']->id]))->assertSessionHasErrors('chart_of_account_id');
        $this->post(route('budget.allocation.store'), $this->item($fx, ['school_id' => $other['school']->id]))->assertSessionHasErrors('school_id');
        $this->assertSame(0, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_only_a_budget_manager_may_open_the_form_or_save_an_item(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($this->viewerOf($fx));

        $this->get(route('budget.allocation.create'))->assertForbidden();
        $this->post(route('budget.allocation.store'), $this->item($fx))->assertForbidden();
        $this->assertSame(0, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_an_item_can_be_edited_until_it_is_closed_and_not_by_another_school(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user'])->post(route('budget.allocation.store'), $this->item($fx))->assertSessionHasNoErrors();
        $item = BudgetAllocation::withoutGlobalScopes()->firstOrFail();

        $this->get(route('budget.allocation.edit', $item))->assertOk();
        $item->update(['closed_at' => now()]);
        $this->get(route('budget.allocation.edit', $item))->assertForbidden();
        $this->actingAs($other['user'])->get(route('budget.allocation.edit', $item))->assertStatus(404);
    }

    public function test_the_balance_lookup_returns_the_position_of_the_own_school_only(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user'])->post(route('budget.allocation.store'), $this->item($fx, ['fiscal_year' => now()->year]))->assertSessionHasNoErrors();

        $this->getJson(route('budget.balance', ['school_id' => $fx['school']->id, 'fund' => 'MOOE']))->assertOk()
            ->assertJson(['has_allocation' => true, 'allocated' => 1000, 'obligated' => 0, 'balance' => 1000]);
        $this->getJson(route('budget.balance', ['school_id' => $fx['school']->id, 'fund' => 'SBFP']))->assertOk()->assertJson(['has_allocation' => false, 'balance' => 0]);
        $this->getJson(route('budget.balance', ['school_id' => $other['school']->id, 'fund' => 'MOOE']))->assertForbidden();
    }
}
