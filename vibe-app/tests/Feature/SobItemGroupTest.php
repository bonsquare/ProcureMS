<?php

namespace Tests\Feature;

use App\Models\AppItem;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** An Item is an AIP activity with one account: its particulars, a free note and any other lines, edited and removed as one. */
class SobItemGroupTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(): array
    {
        return [
            ['particulars' => 'Ready Mix Latex', 'frequency' => 1, 'quantity' => 12, 'unit' => 'gallon', 'unit_cost' => 1300],
            ['particulars' => 'Paint Tray', 'frequency' => 1, 'quantity' => 2, 'unit' => 'pcs', 'unit_cost' => 80],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extras(): array
    {
        return [['particulars' => '35% Labor', 'unit' => 'Labor', 'amount' => 9500]];
    }

    private function draftWithGroup(array $fx, ?string $note = 'POW required'): SobPlan
    {
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $this->lines(), $note, $this->extras());

        return $plan->fresh();
    }

    private function otherAccount(array $fx): ChartOfAccount
    {
        return ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
    }

    public function test_an_item_takes_a_free_note_and_other_lines_that_count_in_the_totals(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);

        $rows = SobItem::orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([false, false, true], $rows->pluck('is_extra')->map(fn ($v) => (bool) $v)->all());
        $this->assertSame(['POW required'], $rows->pluck('note')->unique()->values()->all(), 'the note belongs to the whole Item');
        $extra = $rows[2];
        $this->assertSame(['35% Labor', 'Labor', 9500.0, 9500.0], [$extra->particulars, $extra->unit, (float) $extra->unit_cost, (float) $extra->amount]);

        $group = $this->service()->grouped($plan)[0]['programs'][0]['activities'][0]['accounts'][0];
        $this->assertSame('POW required', $group['note']);
        $this->assertSame(2, $group['items']->count());
        $this->assertSame(1, $group['extras']->count());
        $this->assertSame(15760.0, $group['items_total'], '12 x 1,300 + 2 x 80');
        $this->assertSame(25260.0, $group['total'], 'with the labor line');
        $this->assertSame(25260.0, (float) $this->service()->grouped($plan)[0]['total']);
        $this->assertSame(25260.0, (float) $this->service()->summaryByAccount($plan)->first()['total']);
    }

    public function test_the_note_and_other_lines_are_optional_and_validated(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $this->lines());
        $this->assertSame([null], SobItem::pluck('note')->unique()->values()->all());

        $this->expectException(ValidationException::class);
        $this->service()->addItems($plan, $fx['activities'][1]->id, $fx['account']->id, $this->lines(), null, [['particulars' => '', 'unit' => 'Labor', 'amount' => 100]]);
    }

    public function test_save_group_updates_adds_and_removes_particulars_and_moves_the_item(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $electric = $this->otherAccount($fx);
        [$latex, $tray, $labor] = SobItem::orderBy('id')->get()->all();

        $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, [
            'aip_activity_id' => $fx['activities'][1]->id, 'chart_of_account_id' => $electric->id, 'note' => 'Changed note',
            'lines' => [
                ['id' => $latex->id, 'particulars' => 'Latex', 'frequency' => 1, 'quantity' => 10, 'unit' => 'gallon', 'unit_cost' => 1000],
                ['particulars' => 'Brush', 'frequency' => 1, 'quantity' => 3, 'unit' => 'pcs', 'unit_cost' => 50],
            ],
            'extras' => [['id' => $labor->id, 'particulars' => '30% Labor', 'unit' => 'Labor', 'amount' => 3000]],
        ]);

        $rows = SobItem::orderBy('id')->get();
        $this->assertNull(SobItem::find($tray->id), 'a particulars left out is removed');
        $this->assertSame(['Latex', '30% Labor', 'Brush'], $rows->pluck('particulars')->all());
        $this->assertSame([$fx['activities'][1]->id], $rows->pluck('aip_activity_id')->unique()->values()->all());
        $this->assertSame([$electric->id], $rows->pluck('chart_of_account_id')->unique()->values()->all());
        $this->assertSame(['Changed note'], $rows->pluck('note')->unique()->values()->all());
        $this->assertSame([10000.0, 3000.0, 150.0], $rows->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame($latex->id, $rows[0]->id, 'the row kept its identity');
    }

    public function test_an_invalid_row_saves_nothing_and_a_group_needs_a_particulars(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $before = SobItem::orderBy('id')->get()->map->only(['id', 'particulars', 'amount'])->all();
        $post = fn (array $lines) => $this->service()->saveGroup($plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $lines, 'extras' => []]);

        foreach ([[['particulars' => 'x', 'frequency' => 1, 'quantity' => 0, 'unit' => 'pcs', 'unit_cost' => 1]], []] as $lines) {
            try {
                $post($lines);
                $this->fail('refused');
            } catch (ValidationException) {
                $this->assertSame($before, SobItem::orderBy('id')->get()->map->only(['id', 'particulars', 'amount'])->all(), 'all or nothing');
            }
        }
    }

    public function test_delete_group_removes_every_row_of_the_item_only(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $this->service()->addItems($plan, $fx['activities'][1]->id, $fx['account']->id, $this->lines());

        $this->service()->deleteGroup($plan, $fx['activities'][0]->id, $fx['account']->id);

        $this->assertSame(2, SobItem::count());
        $this->assertSame([$fx['activities'][1]->id], SobItem::pluck('aip_activity_id')->unique()->values()->all());
    }

    public function test_an_approved_sob_can_remove_an_item_which_releases_its_budget(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $this->service()->approve($plan, $fx['user']);

        $this->service()->deleteGroup($plan->fresh(), $fx['activities'][0]->id, $fx['account']->id);

        $this->assertSame(0, SobItem::count());
        $this->assertSame(0.0, (float) BudgetAllocation::withoutGlobalScopes()->sum('q1_amount'));
    }

    public function test_other_lines_count_in_the_budget_but_are_not_copied_to_the_app(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $this->service()->approve($plan, $fx['user']);

        $this->assertSame(25260.0, (float) BudgetAllocation::withoutGlobalScopes()->sum('q1_amount'));
        $this->post(route('planning.app.generate'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'sob_item_ids' => SobItem::withoutGlobalScopes()->where('is_extra', false)->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertSame(['Ready Mix Latex', 'Paint Tray'], AppItem::orderBy('id')->pluck('procurement_item')->all(), 'labor is not purchased');
    }

    public function test_the_item_routes_edit_and_remove_a_whole_item_for_managers_in_their_own_school_only(): void
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherPlan = $this->draftWithGroup($other);
        $fx = SobFixture::make(['slug' => uniqid('own')]);
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $key = ['old_activity' => $fx['activities'][0]->id, 'old_account' => $fx['account']->id];
        $payload = $key + ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'note' => 'Edited', 'lines' => $this->lines(), 'extras' => $this->extras()];

        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);
        $this->put(route('planning.sob.groups.update', $plan), $payload)->assertForbidden();
        $this->delete(route('planning.sob.groups.destroy', $plan), $key)->assertForbidden();

        $this->actingAs($fx['user']);
        $this->put(route('planning.sob.groups.update', $plan), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['Edited'], SobItem::pluck('note')->unique()->values()->all());
        $this->put(route('planning.sob.groups.update', $otherPlan), $payload)->assertStatus(404);
        $this->delete(route('planning.sob.groups.destroy', $plan), $key)->assertSessionHasNoErrors();
        $this->assertSame(0, SobItem::count());
    }

    public function test_the_page_edits_a_whole_item_inline_without_a_popup_or_per_particulars_buttons(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-edit-item'), 'one Edit item for the one Item');
        $this->assertStringNotContainsString('absolute z-10', $html, 'no floating pop-up window');
        $this->assertStringNotContainsString('/planning/sob/items/', $html, 'no per-particulars edit or remove');
        $this->assertStringContainsString('name="lines[1][particulars]"', $html, 'both particulars are in the one form');
        $this->assertStringContainsString('name="extras[0][particulars]"', $html);
        $this->assertStringContainsString('name="note"', $html);
        $this->assertStringContainsString('POW required', $html);
        $this->assertStringContainsString('Remove item', $html);
    }

    public function test_the_print_groups_by_account_title_with_the_note_other_lines_and_a_sub_total_only_for_filled_pillars(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $this->otherAccount($fx)->id, [['particulars' => 'Electricity', 'frequency' => 3, 'quantity' => 1, 'unit' => 'monthly', 'unit_cost' => 2000]]);

        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<td class="account"[^>]*>Electricity Expenses<\/td>/', $html), 'the account title is written once for its particulars');
        $this->assertMatchesRegularExpression('/<tr class="program-row"><td colspan="7">Program One<\/td><\/tr>/', $html, 'the program is its own bold row, as in the workbook');
        $this->assertMatchesRegularExpression('/<td class="account" rowspan="\d+">POW required<\/td>/', $html, 'the note stands in for the account title');
        $this->assertMatchesRegularExpression('/<td class="account" rowspan="\d+">Electricity Expenses<\/td>/', $html);
        $this->assertStringContainsString('35% Labor', $html);
        $this->assertSame(2, substr_count($html, 'class="sub item-sub"'), 'only the Item that has other lines has a Sub-Total of its particulars and a Sub-Total with its other lines (the labor)');
    }

    public function test_a_pillar_without_entries_has_no_sub_total(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->draftWithGroup($fx);

        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, '<tr class="sub pillar-sub">'), 'one Item with other lines already ends with the same Sub-Total, so the pillar does not repeat it');

        $this->service()->addItems($plan, $fx['activities'][0]->id, $this->otherAccount($fx)->id, [['particulars' => 'Electricity', 'frequency' => 3, 'quantity' => 1, 'unit' => 'monthly', 'unit_cost' => 2000]]);
        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<tr class="sub pillar-sub">'), 'only Access has entries, so only it has a Sub-Total');
    }

    public function test_an_empty_pillar_prints_its_bar_only(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        $this->assertSame(5, substr_count($html, '<tr class="pillar-row'), 'every pillar is printed');
        $this->assertSame(0, substr_count($html, 'class="program-row"'), 'a pillar without entries has no PPA row');
        $this->assertSame(0, substr_count($html, 'class="sub '), 'and no Sub-Total');
    }
}
