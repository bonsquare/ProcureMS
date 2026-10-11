<?php

namespace Tests\Feature;

use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\AuditLog;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** An approved APP can be unlocked into a draft, edited, and deleted when no Purchase Request draws from it. */
class AppUnlockTest extends TestCase
{
    use RefreshDatabase;

    private function appWithItem(array $fx, string $status = 'approved'): AppItem
    {
        $appPlan = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => $status]);

        return AppItem::create([
            'organization_id' => $fx['organization']->id, 'app_plan_id' => $appPlan->id, 'procurement_item' => 'Bond paper',
            'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 1000, 'fund_source' => 'MOOE',
        ]);
    }

    private function draw(array $fx, AppItem $item): void
    {
        $request = ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-APP', 'title' => 'Paper', 'amount' => 500, 'status' => 'submitted']);
        ProcurementRequestItem::create(['organization_id' => $fx['organization']->id, 'procurement_request_id' => $request->id, 'app_item_id' => $item->id, 'name' => 'Bond paper', 'quantity' => 5, 'unit' => 'ream', 'unit_price' => 100, 'total' => 500]);
    }

    public function test_unlocking_an_approved_app_makes_it_a_draft_whose_items_can_be_edited_and_removed(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $item = $this->appWithItem($fx);

        $this->put(route('planning.app.items.update', $item), ['procurement_item' => 'Short paper', 'quantity' => 4, 'unit' => 'ream', 'estimated_unit_cost' => 50])->assertSessionHasErrors('planning');

        $this->post(route('planning.app.unlock', $item->plan))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $item->plan->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'app_unlocked')->exists());

        $this->put(route('planning.app.items.update', $item), ['procurement_item' => 'Short paper', 'quantity' => 4, 'unit' => 'ream', 'estimated_unit_cost' => 50])->assertSessionHasNoErrors();
        $this->assertSame([200.0, 'Short paper'], [(float) $item->fresh()->estimated_total_cost, $item->fresh()->procurement_item]);
        $this->delete(route('planning.app.items.destroy', $item))->assertSessionHasNoErrors();
        $this->assertSame(0, AppItem::count());
    }

    public function test_only_an_approved_app_can_be_unlocked_by_a_manager_of_its_own_school(): void
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherPlanId = $this->appWithItem($other)->app_plan_id;
        $fx = SobFixture::make(['slug' => uniqid('own')]);
        $this->actingAs($fx['user']);
        $draft = $this->appWithItem($fx, 'draft');

        $this->post(route('planning.app.unlock', $draft->plan))->assertSessionHasErrors('planning');
        $this->assertContains($this->post(route('planning.app.unlock', $otherPlanId))->getStatusCode(), [403, 404]);
        $this->assertSame('approved', AppPlan::withoutGlobalScopes()->find($otherPlanId)->status);

        $draft->plan->update(['status' => 'approved']);
        $approved = $draft;
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);
        $this->post(route('planning.app.unlock', $approved->plan))->assertForbidden();
        $this->assertSame('approved', $approved->plan->fresh()->status);
    }

    public function test_the_page_shows_unlock_only_on_an_approved_app(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $item = $this->appWithItem($fx);

        $approved = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $this->assertStringContainsString(route('planning.app.unlock', $item->plan), $approved);
        $this->assertStringNotContainsString(route('planning.app.approve', $item->plan), $approved);

        $item->plan->update(['status' => 'draft']);
        $draft = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('planning.app.unlock', $item->plan), $draft);
        $this->assertStringContainsString(route('planning.app.destroy', $item->plan), $draft);
    }

    public function test_a_draft_app_can_be_deleted_unless_a_purchase_request_draws_from_it(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $item = $this->appWithItem($fx, 'draft');
        $this->draw($fx, $item);

        $this->delete(route('planning.app.destroy', $item->plan))->assertSessionHasErrors('planning');
        $this->assertSame(1, AppItem::count());

        $item->requestItems()->delete();
        $this->delete(route('planning.app.destroy', $item->plan))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([0, 0], [AppItem::count(), AppPlan::count()]);

        $approved = $this->appWithItem($fx);
        $this->delete(route('planning.app.destroy', $approved->plan))->assertSessionHasErrors('planning');
        $this->assertSame(1, AppPlan::count(), 'an approved APP must be unlocked first');
    }
}
