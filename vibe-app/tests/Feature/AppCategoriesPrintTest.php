<?php

namespace Tests\Feature;

use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\SobItem;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** SOB particulars carry one of six categories into the APP, which groups by them and prints without zero amounts. */
class AppCategoriesPrintTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORIES = ['Common Office Supplies', 'Drugs and Medicines', 'Construction Components and Supplies', 'Paper Materials and Products', 'Common ICT Equipment', 'Consumables', 'Other Categories'];

    private function service(): SobService
    {
        return app(SobService::class);
    }

    /** @return array<string, mixed> */
    private function line(string $particulars, ?string $category, float $unitCost = 100, float $quantity = 10): array
    {
        return ['particulars' => $particulars, 'frequency' => 1, 'quantity' => $quantity, 'unit' => 'pcs', 'unit_cost' => $unitCost] + ($category === null ? [] : ['category' => $category]);
    }

    /** An approved SOB with items in several categories and a generated draft APP. */
    private function generated(array $fx): AppPlan
    {
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [
            $this->line('Printer ink', 'Common ICT Equipment', 200, 5),
            $this->line('Bond paper', 'Paper Materials and Products'),
            $this->line('Ballpen', 'Common Office Supplies', 15, 20),
            $this->line('Mystery item', 'Other Categories'),
            $this->line('Travel budget', ''),
        ]);
        $this->service()->approve($plan->fresh(), $fx['user']);
        $this->post(route('planning.app.generate'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'sob_item_ids' => SobItem::withoutGlobalScopes()->where('is_extra', false)->pluck('id')->all()])->assertSessionHasNoErrors();

        return AppPlan::firstOrFail();
    }

    public function test_a_particulars_takes_one_of_the_seven_categories_or_none(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [
            $this->line('Paracetamol', 'Drugs and Medicines'), $this->line('Cement', 'Construction Components and Supplies'), $this->line('No category', ''),
        ], null, [['particulars' => '35% Labor', 'unit' => 'Labor', 'amount' => 500]]);

        $this->assertSame(['Drugs and Medicines', 'Construction Components and Supplies', null, null], SobItem::orderBy('id')->pluck('category')->all(), 'a line left at "-" and a labor line have no category');
        $this->expectException(ValidationException::class);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [$this->line('Bad', 'Gadgets')]);
    }

    public function test_the_form_offers_the_seven_categories_and_a_dash_for_every_particulars(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [$this->line('Paracetamol', 'Drugs and Medicines')]);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertStringContainsString('name="lines[0][category]"', $html);
        preg_match_all('/<select name="lines\[0\]\[category\]"[^>]*>(.*?)<\/select>/s', $html, $selects);
        $this->assertCount(2, $selects[1], 'the add form and the edit form');
        foreach ($selects[1] as $select) {
            preg_match_all('/<option[^>]*>([^<]*)<\/option>/', $select, $options);
            $this->assertSame(['-', ...self::CATEGORIES], array_map(fn ($text) => html_entity_decode(trim($text)), $options[1]));
        }
        $this->assertMatchesRegularExpression('/<option value="Drugs and Medicines" selected>/', $html, 'the saved category is selected when editing');
    }

    public function test_the_category_travels_to_the_app_and_follows_a_later_edit(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $app = $this->generated($fx);

        $this->assertEqualsCanonicalizing(['Common ICT Equipment', 'Paper Materials and Products', 'Common Office Supplies', 'Other Categories'], $app->items()->pluck('category')->all(), 'the line left at "-" (a travel budget) is not put in the APP');
        $this->assertSame(0, $app->items()->where('procurement_item', 'Travel budget')->count());

        $paper = SobItem::where('particulars', 'Bond paper')->firstOrFail();
        $group = ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'extras' => [], 'lines' => SobItem::orderBy('id')->get()->map(fn (SobItem $i) => [
            'id' => $i->id, 'particulars' => $i->particulars, 'frequency' => 1, 'quantity' => $i->quantity, 'unit' => 'pcs', 'unit_cost' => $i->unit_cost,
            'category' => $i->id === $paper->id ? 'Common Office Supplies' : $i->category,
        ])->all()];
        $this->service()->saveGroup($paper->plan, $fx['activities'][0]->id, $fx['account']->id, $group);

        $this->assertSame('Common Office Supplies', AppItem::where('sob_item_id', $paper->id)->value('category'));
    }

    public function test_the_app_item_dialog_can_change_the_category_but_only_to_a_listed_one(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $item = $this->generated($fx)->items()->where('procurement_item', 'Ballpen')->firstOrFail();
        $payload = fn (string $category) => ['procurement_item' => 'Ballpen', 'quantity' => 20, 'unit' => 'pcs', 'estimated_unit_cost' => 15, 'category' => $category];

        $this->put(route('planning.app.items.update', $item), $payload('Drugs and Medicines'))->assertSessionHasNoErrors();
        $this->assertSame('Drugs and Medicines', $item->fresh()->category);
        $this->put(route('planning.app.items.update', $item), $payload('Gadgets'))->assertSessionHasErrors('category');
    }

    public function test_the_app_page_groups_its_lines_under_the_category_headings_in_order(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->generated($fx);

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $app = substr($html, strpos($html, 'Annual Procurement Plan · FY'));
        $app = substr($app, 0, strpos($app, '</section>'));
        $positions = array_map(fn ($category) => strpos($app, '>'.$category.'<'), ['Common Office Supplies', 'Paper Materials and Products', 'Common ICT Equipment', 'Other Categories']);

        $this->assertNotContains(false, $positions, 'a heading for each category that has lines');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'in the order Common Office Supplies, Paper Materials and Products, Common ICT Equipment, Other Categories');
        $this->assertFalse(strpos($app, '>Drugs and Medicines<'), 'an empty category has no heading');
        $this->assertStringContainsString(route('planning.app.print', AppPlan::first()), $html);
    }

    public function test_the_printed_app_hides_zero_amounts_and_empty_categories_and_totals_what_is_left(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $app = $this->generated($fx);
        $app->items()->where('procurement_item', 'Ballpen')->update(['estimated_unit_cost' => 0, 'estimated_total_cost' => 0]);

        $html = $this->get(route('planning.app.print', $app))->assertOk()->getContent();

        $this->assertStringContainsString('ANNUAL PROCUREMENT PLAN', $html);
        $this->assertStringContainsString('data-official-page', $html);
        $this->assertStringContainsString('data-doc="app"', $html);
        $this->assertStringContainsString('Printer ink', $html);
        $this->assertStringNotContainsString('Ballpen', $html, 'a zero-amount line is not printed');
        $this->assertStringNotContainsString('>Common Office Supplies<', $html, 'and a category left empty is not printed');
        $this->assertStringNotContainsString('>Drugs and Medicines<', $html);
        $this->assertSame(['Paper Materials and Products', 'Common ICT Equipment', 'Other Categories'], array_values(array_filter(self::CATEGORIES, fn ($category) => str_contains($html, '>'.strtoupper($category).'<') || str_contains($html, '>'.$category.'<'))));
        $this->assertStringContainsString('3,000.00', $html, 'the grand total of what is printed (1,000 + 1,000 + 1,000)');
        $this->assertMatchesRegularExpression('/thead th\s*\{[^}]*background:\s*#00af50/i', $html);
        $this->assertStringContainsString('"APP FY 2026"', $html);
        $this->assertStringNotContainsString('counter(page)', $html, 'no page numbers are printed');
        $this->assertStringContainsString('class="masthead-logo masthead-logo-left"', $html);
        $this->assertStringContainsString('class="masthead-logo masthead-logo-right"', $html);
    }

    public function test_the_app_signatories_take_a_typed_role_name_and_position(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $app = $this->generated($fx);

        $this->put(route('planning.app.signatories', $app), [
            'prepared_by_name' => 'Pat School', 'prepared_by_position' => 'Property Custodian', 'prepared_by_role' => '',
            'recommended_by_name' => 'Dana District', 'recommended_by_position' => 'PSDS', 'recommended_by_role' => 'Endorsed by',
            'approved_by_name' => 'Sam Division', 'approved_by_position' => 'SDS', 'approved_by_role' => 'Approved',
        ])->assertSessionHasNoErrors();

        $html = $this->get(route('planning.app.print', $app))->assertOk()->getContent();
        foreach (['>Prepared by:<', '>Endorsed by:<', '>Approved:<', 'Dana District', 'Property Custodian'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('>Recommending Approval:<', $html);
        $page = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->getContent();
        $this->assertStringContainsString('name="recommended_by_role"', $page);
    }

    public function test_printing_and_signatories_respect_permissions_and_school_isolation(): void
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherApp = AppPlan::create(['organization_id' => $other['organization']->id, 'school_id' => $other['school']->id, 'fiscal_year' => 2026, 'status' => 'draft']);
        $fx = SobFixture::make(['slug' => uniqid('own')]);
        $this->actingAs($fx['user']);
        $app = $this->generated($fx);

        $this->assertContains($this->get(route('planning.app.print', $otherApp))->getStatusCode(), [403, 404]);
        $this->assertContains($this->put(route('planning.app.signatories', $otherApp), ['prepared_by_name' => 'X'])->getStatusCode(), [403, 404]);

        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);
        $this->get(route('planning.app.print', $app))->assertOk();
        $this->put(route('planning.app.signatories', $app), ['prepared_by_name' => 'X'])->assertForbidden();
    }

    public function test_a_line_changed_to_a_dash_leaves_the_app_and_comes_back_with_a_category(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->generated($fx);
        $ballpen = SobItem::where('particulars', 'Ballpen')->firstOrFail();
        $change = fn (?string $category) => $this->service()->saveGroup($ballpen->plan, $fx['activities'][0]->id, $fx['account']->id, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'extras' => [], 'lines' => SobItem::orderBy('id')->get()->map(fn (SobItem $i) => [
            'id' => $i->id, 'particulars' => $i->particulars, 'frequency' => 1, 'quantity' => $i->quantity, 'unit' => 'pcs', 'unit_cost' => $i->unit_cost,
            'category' => $i->id === $ballpen->id ? $category : $i->category,
        ])->all()]);

        $change(null);
        $this->assertNull($ballpen->fresh()->category);
        $this->assertSame(0, AppItem::where('sob_item_id', $ballpen->id)->count(), 'the line left the APP');

        $change('Consumables');
        $this->assertSame('Consumables', AppItem::where('sob_item_id', $ballpen->id)->value('category'), 'and came back under its category');
    }
}
