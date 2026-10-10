<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

class SobFlowTest extends TestCase
{
    use RefreshDatabase;

    private function draft(array $fx, int $quarter = 1): SobPlan
    {
        return app(SobService::class)->create($fx['school'], 2026, $quarter, 'MOOE', $fx['user']);
    }

    private function line(array $fx, array $override = []): array
    {
        return $override + ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100];
    }

    private function planningPage(array $fx)
    {
        return $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]));
    }

    public function test_a_quarter_card_creates_a_draft_sob_and_opens_it(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->planningPage($fx)->assertOk()->assertSee('School Operating Budget')->assertSee('1ST QUARTER')->assertSee('2ND QUARTER')->assertSee('3RD QUARTER')->assertSee('4TH QUARTER')->assertSee(route('planning.sob.store'), false);

        $response = $this->post(route('planning.sob.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE']);

        $plan = SobPlan::withoutGlobalScopes()->sole();
        $response->assertRedirect(route('planning.sob.show', $plan));
        $this->assertSame('draft', $plan->status);
        $this->planningPage($fx)->assertSee(route('planning.sob.show', $plan), false);
        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee('1ST QUARTER')->assertSee('MOOE');
    }

    public function test_creating_without_an_approved_aip_or_twice_is_refused(): void
    {
        $fx = SobFixture::make(['status' => 'draft']);
        $ok = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('planning.sob.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE'])->assertSessionHasErrors('planning');
        $this->assertSame(0, SobPlan::withoutGlobalScopes()->count());

        $this->actingAs($ok['user']);
        $payload = ['school_id' => $ok['school']->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'MOOE'];
        $this->post(route('planning.sob.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('planning.sob.store'), $payload)->assertSessionHasErrors('planning');
        $this->post(route('planning.sob.store'), ['quarter' => 9] + $payload)->assertSessionHasErrors();
    }

    public function test_items_can_be_added_edited_and_deleted_while_draft_and_totals_show(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        $this->actingAs($fx['user']);

        $this->post(route('planning.sob.items.store', $plan), $this->line($fx))->assertSessionHasNoErrors()->assertRedirect();
        $item = SobItem::withoutGlobalScopes()->sole();
        $this->assertEquals(1000, $item->amount);

        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee('Bond paper')->assertSee('1,000.00')->assertSee('Summary per object of expenditure')->assertSee('Office Supplies Expenses')->assertSee('Program One')->assertSee('Activity 1');

        $this->put(route('planning.sob.items.update', $item), $this->line($fx, ['quantity' => 4, 'unit_cost' => 50, 'particulars' => 'Short paper']))->assertSessionHasNoErrors();
        $this->assertSame(['Short paper', '200.00'], [$item->fresh()->particulars, (string) $item->fresh()->amount]);
        $this->post(route('planning.sob.items.store', $plan), $this->line($fx, ['quantity' => 0]))->assertSessionHasErrors();

        $this->delete(route('planning.sob.items.destroy', $item))->assertRedirect();
        $this->assertSame(0, SobItem::withoutGlobalScopes()->count());
    }

    public function test_the_header_can_be_changed_while_draft(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        $this->actingAs($fx['user']);

        $this->put(route('planning.sob.update', $plan), ['fund_source' => 'SEF', 'prepared_by_name' => 'New Head', 'prepared_by_position' => 'Principal'])->assertSessionHasNoErrors();

        $this->assertSame(['SEF', 'New Head', 'Principal'], [$plan->fresh()->fund_source, $plan->fresh()->prepared_by_name, $plan->fresh()->prepared_by_position]);
    }

    public function test_the_page_warns_when_an_activity_goes_over_its_aip_amount_but_saves(): void
    {
        $fx = SobFixture::make(['quarterAmount' => 1000]);
        $plan = $this->draft($fx);
        $this->actingAs($fx['user']);

        $this->post(route('planning.sob.items.store', $plan), $this->line($fx, ['quantity' => 20]))->assertSessionHasNoErrors();

        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());
        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee('Over the AIP amount');
    }

    public function test_approve_creates_allotments_and_locks_the_sob(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        $item = app(SobService::class)->addItem($plan, $this->line($fx));
        $this->actingAs($fx['user']);

        $this->post(route('planning.sob.approve', $plan))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
        $this->post(route('planning.sob.items.store', $plan), $this->line($fx))->assertSessionHasErrors('planning');
        $this->put(route('planning.sob.items.update', $item), $this->line($fx))->assertSessionHasErrors('planning');
        $this->delete(route('planning.sob.items.destroy', $item))->assertSessionHasErrors('planning');
        $this->put(route('planning.sob.update', $plan), ['fund_source' => 'SEF'])->assertSessionHasErrors('planning');
        $this->get(route('planning.sob.show', $plan))->assertOk()->assertDontSee('id="sob-item-form"', false)->assertSee('Approved');
    }

    public function test_a_draft_sob_can_be_deleted_and_an_approved_one_cannot(): void
    {
        $fx = SobFixture::make();
        $draft = $this->draft($fx, 1);
        $approved = $this->draft($fx, 2);
        app(SobService::class)->addItem($approved, $this->line($fx));
        app(SobService::class)->approve($approved->fresh(), $fx['user']);
        $this->actingAs($fx['user']);

        $this->delete(route('planning.sob.destroy', $approved))->assertSessionHasErrors('planning');
        $this->assertNotNull(SobPlan::withoutGlobalScopes()->find($approved->id));

        $this->delete(route('planning.sob.destroy', $draft))->assertRedirect();
        $this->assertNull(SobPlan::withoutGlobalScopes()->find($draft->id));
    }

    public function test_permissions_and_school_isolation(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $plan = $this->draft($fx);
        $item = app(SobService::class)->addItem($plan, $this->line($fx));
        $viewer = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'school_staff']);

        $this->actingAs($viewer);
        $this->post(route('planning.sob.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'quarter' => 3, 'fund_source' => 'MOOE'])->assertForbidden();
        $this->post(route('planning.sob.items.store', $plan), $this->line($fx))->assertForbidden();
        $this->put(route('planning.sob.update', $plan), ['fund_source' => 'SEF'])->assertForbidden();
        $this->post(route('planning.sob.approve', $plan))->assertForbidden();
        $this->delete(route('planning.sob.destroy', $plan))->assertForbidden();

        $this->actingAs($other['user']);
        $this->get(route('planning.sob.show', $plan))->assertStatus(404);
        $this->post(route('planning.sob.items.store', $plan), $this->line($other))->assertStatus(404);
        $this->put(route('planning.sob.items.update', $item), $this->line($other))->assertStatus(404);
        $this->delete(route('planning.sob.items.destroy', $item))->assertStatus(404);
        $this->put(route('planning.sob.update', $plan), ['fund_source' => 'SEF'])->assertStatus(404);
        $this->post(route('planning.sob.approve', $plan))->assertStatus(404);
        $this->delete(route('planning.sob.destroy', $plan))->assertStatus(404);
        $this->post(route('planning.sob.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'quarter' => 4, 'fund_source' => 'MOOE'])->assertSessionHasErrors('school_id');
        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());
    }

    public function test_html_in_text_is_escaped_on_the_sob_page(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        app(SobService::class)->addItem($plan, $this->line($fx, ['particulars' => '<script>alert(1)</script>']));
        $this->actingAs($fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
