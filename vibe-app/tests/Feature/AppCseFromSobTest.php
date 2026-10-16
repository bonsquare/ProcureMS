<?php

namespace Tests\Feature;

use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\CseCatalogItem;
use App\Models\SobItem;
use App\Services\AppCseService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The SOB particulars of the Common Office Supplies category are saved in the APP-CSE, and lines with no quantity are not shown or printed. */
class AppCseFromSobTest extends TestCase
{
    use RefreshDatabase;

    private function addLines(array $fx, $plan, array $lines): void
    {
        $this->post(route('planning.sob.items.store', $plan), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $lines])
            ->assertSessionHasNoErrors();
    }

    private function approve($plan): void
    {
        $this->post(route('planning.sob.approve', $plan))->assertSessionHasNoErrors();
    }

    private function fromSob(): array
    {
        return AppCseItem::where('from_sob', true)->orderBy('id')->get()
            ->map(fn (AppCseItem $line) => [$line->description, $line->unit, (float) $line->unit_price, array_map('floatval', $line->months())])->all();
    }

    public function test_every_line_of_the_sob_is_written_into_the_app_cse_under_its_category_in_the_first_month_of_its_quarter(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $q4 = app(SobService::class)->create($fx['school'], 2026, 4, 'MOOE', $fx['user']);

        $this->addLines($fx, $q4, [
            ['particulars' => 'Bond Paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'Ream', 'unit_cost' => 300, 'category' => 'Common Office Supplies'],
            ['particulars' => 'Laptop', 'frequency' => 1, 'quantity' => 1, 'unit' => 'unit', 'unit_cost' => 40000, 'category' => 'Common ICT Equipment'],
        ]);
        $this->assertSame([], $this->fromSob(), 'a draft SOB is not in the APP-CSE yet');
        $this->approve($q4);

        $plan = AppCsePlan::where('school_id', $fx['school']->id)->where('fiscal_year', 2026)->first();
        $this->assertNotNull($plan, 'the APP-CSE of the year is made when there is none');
        $october = array_fill(0, 12, 0.0);
        $october[9] = 10.0;
        $laptop = $october;
        $laptop[9] = 1.0;
        $this->assertSame([['Bond Paper', 'Ream', 300.0, $october], ['Laptop', 'unit', 40000.0, $laptop]], $this->fromSob(), 'every category, in October, at the SOB unit cost');
        $lines = AppCseItem::where('from_sob', true)->orderBy('sort_order')->get();
        $this->assertTrue($lines->every->isCustom(), 'Part II lines');
        $this->assertSame(['Common Office Supplies', 'Common ICT Equipment'], $lines->pluck('category')->all(), 'each under its own category');
    }

    public function test_a_line_left_at_a_dash_is_not_written_into_the_app_cse(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $q4 = app(SobService::class)->create($fx['school'], 2026, 4, 'MOOE', $fx['user']);

        $this->addLines($fx, $q4, [
            ['particulars' => 'Bond Paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'Ream', 'unit_cost' => 300, 'category' => 'Common Office Supplies'],
            ['particulars' => 'Travel Budget', 'frequency' => 1, 'quantity' => 1, 'unit' => 'lot', 'unit_cost' => 5000, 'category' => ''],
        ]);
        $this->approve($q4);

        $this->assertSame(['Bond Paper'], array_column($this->fromSob(), 0), 'a line that is not goods ("-") is not in the APP-CSE');
    }

    public function test_the_lines_follow_the_sob_when_it_changes_and_the_same_particulars_are_added_up(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $service = app(SobService::class);
        $q1 = $service->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $q2 = $service->create($fx['school'], 2026, 2, 'MOOE', $fx['user']);
        $this->addLines($fx, $q1, [['particulars' => 'Bond Paper', 'frequency' => 2, 'quantity' => 5, 'unit' => 'Ream', 'unit_cost' => 300, 'category' => 'Common Office Supplies']]);
        $this->addLines($fx, $q2, [['particulars' => 'bond paper ', 'frequency' => 1, 'quantity' => 4, 'unit' => 'ream', 'unit_cost' => 300, 'category' => 'Common Office Supplies']]);
        $this->approve($q1);
        $this->approve($q2);

        $lines = $this->fromSob();
        $this->assertCount(1, $lines, 'the same particulars, unit and cost are one line');
        $this->assertSame([10.0, 0.0, 0.0, 4.0], [$lines[0][3][0], $lines[0][3][1], $lines[0][3][2], $lines[0][3][3]], 'January 2 x 5, April 4');

        $this->put(route('planning.sob.update', $q2), ['fund_source' => 'MOOE', 'quarter' => 3])->assertSessionHasNoErrors();
        $this->assertSame(4.0, $this->fromSob()[0][3][6], 'moved to July with its SOB');

        $this->post(route('planning.sob.reopen', $q1))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->fromSob()[0][3][0], 'a reopened (draft) SOB leaves the APP-CSE');
        $this->approve($q1);
        $this->assertSame(10.0, $this->fromSob()[0][3][0], 'and comes back when approved again');
        $this->delete(route('planning.sob.items.destroy', SobItem::where('sob_plan_id', $q1->id)->first()))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->fromSob()[0][3][0], 'removed from January with its SOB line');
        $this->delete(route('planning.sob.destroy', $q2))->assertSessionHasNoErrors();
        $this->assertSame([], $this->fromSob(), 'gone with the last SOB that had it');
    }

    public function test_a_line_from_the_sob_is_changed_in_the_sob_and_an_approved_app_cse_follows_it_as_a_draft(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $q1 = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->addLines($fx, $q1, [['particulars' => 'Bond Paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'Ream', 'unit_cost' => 300, 'category' => 'Common Office Supplies']]);
        $this->approve($q1);
        $line = AppCseItem::where('from_sob', true)->firstOrFail();

        try {
            app(AppCseService::class)->updateItem($line, ['m1' => 99] + array_fill_keys(['m2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'], 0));
            $this->fail('a line from the SOB cannot be changed in the APP-CSE');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Change it in the SOB', $exception->getMessage());
        }
        $page = $this->get(route('planning.app-cse.show', $line->plan))->assertOk()->getContent();
        $this->assertStringContainsString('From the SOB: change it in the SOB', $page);

        $line->plan->update(['status' => 'approved']);
        app(AppCseService::class)->syncFromSob($fx['school'], 2026, $fx['user']);
        $this->assertSame('approved', $line->plan->fresh()->status, 'nothing changed in the SOB: the approved APP-CSE is left as it is');

        $this->addLines($fx, $q1, [['particulars' => 'Folder', 'frequency' => 1, 'quantity' => 3, 'unit' => 'pc', 'unit_cost' => 10, 'category' => 'Common Office Supplies']]);
        $this->assertCount(2, $this->fromSob(), 'a new line of the SOB comes into the APP-CSE');
        $this->assertSame('draft', $line->plan->fresh()->status, 'and the approved APP-CSE goes back to a draft so it follows the SOB');
    }

    public function test_lines_with_no_quantity_are_folded_on_the_page_and_not_printed(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);
        $priced = CseCatalogItem::where('catalog_year', 2026)->whereNotNull('price')->orderBy('sort_order')->get();
        $used = app(AppCseService::class)->addCatalogItem($plan, $priced[0]->id);
        app(AppCseService::class)->updateItem($used, ['m1' => 2] + array_fill_keys(['m2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12'], 0));
        app(AppCseService::class)->addCatalogItem($plan, $priced[1]->id);
        $zeroOwn = app(AppCseService::class)->addCustomItem($plan, ['category' => 'Hardware', 'description' => 'Unused nails', 'unit' => 'kg', 'unit_price' => 5]);

        $print = $this->get(route('planning.app-cse.print', $plan))->assertOk()->getContent();
        $this->assertSame(1, substr_count($print, 'class="catalog-row"'), 'only the catalog row with a quantity');
        $this->assertStringContainsString($priced[0]->code, $print);
        $this->assertStringNotContainsString($priced[1]->code, $print);
        $this->assertStringNotContainsString('Unused nails', $print);

        $page = $this->get(route('planning.app-cse.show', $plan))->assertOk()->getContent();
        $this->assertSame(2, substr_count($page, '1 item with no quantity yet (not printed)'), 'one folded in Part I and one in Part II');
        $this->assertStringContainsString(route('planning.app-cse.items.update', $zeroOwn), $page, 'still there to be given a quantity');
    }
}
