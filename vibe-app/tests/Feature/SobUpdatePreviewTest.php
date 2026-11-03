<?php

namespace Tests\Feature;

use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The preview of a changed SOB line or line group draws the change and saves nothing. */
class SobUpdatePreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array, 1: SobPlan, 2: SobItem} */
    private function withItem(?array $fx = null): array
    {
        $fx ??= SobFixture::make();
        $sob = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->actingAs($fx['user'])->post(route('planning.sob.items.store', $sob), $this->line($fx, 'Original Ballpen'))->assertSessionHasNoErrors();

        return [$fx, $sob, SobItem::firstOrFail()];
    }

    private function line(array $fx, string $particulars): array
    {
        return ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => $particulars, 'frequency' => 1, 'quantity' => 4, 'unit' => 'box', 'unit_cost' => 250];
    }

    public function test_the_item_preview_shows_the_new_text_and_keeps_the_saved_item(): void
    {
        [$fx, , $item] = $this->withItem();

        $html = $this->post(route('planning.sob.items.update.preview', $item), $this->line($fx, 'Changed Marker'))->assertOk()->getContent();

        $this->assertStringContainsString('Changed Marker', $html);
        $this->assertSame('Original Ballpen', $item->fresh()->particulars, 'a preview is rolled back');
        $this->assertSame(1, SobItem::count());
    }

    public function test_the_item_preview_of_a_bad_entry_still_answers_and_saves_nothing(): void
    {
        [, , $item] = $this->withItem();

        $this->post(route('planning.sob.items.update.preview', $item), ['particulars' => ''])->assertOk();
        $this->assertSame('Original Ballpen', $item->fresh()->particulars);
    }

    public function test_the_group_preview_keeps_the_saved_lines(): void
    {
        [$fx, $sob, $item] = $this->withItem();

        $this->post(route('planning.sob.groups.preview', $sob), [
            'old_activity' => $item->aip_activity_id, 'old_account' => $item->chart_of_account_id,
            'aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'note' => 'Preview note',
            'lines' => [$item->id => ['particulars' => 'Group Changed', 'frequency' => 1, 'quantity' => 1, 'unit' => 'pc', 'unit_cost' => 10]],
        ])->assertOk();

        $this->assertSame('Original Ballpen', $item->fresh()->particulars);
        $this->assertSame(1, SobItem::count());
    }

    public function test_a_role_without_planning_manage_cannot_preview_and_another_schools_item_is_not_found(): void
    {
        $other = SobFixture::make();
        [$fx, $sob, $item] = $this->withItem();
        $viewer = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->post(route('planning.sob.items.update.preview', $item), $this->line($fx, 'x'))->assertForbidden();
        $this->post(route('planning.sob.groups.preview', $sob), [])->assertForbidden();
        $this->actingAs($other['user'])->post(route('planning.sob.items.update.preview', $item), $this->line($other, 'x'))->assertNotFound();
        $this->assertSame('Original Ballpen', $item->fresh()->particulars);
    }
}
