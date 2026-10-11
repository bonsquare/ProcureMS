<?php

namespace Tests\Feature;

use App\Models\AppCsePlan;
use App\Models\CseCatalogItem;
use App\Models\SchoolStaff;
use App\Models\User;
use App\Services\AppCseService;
use App\Services\CseCatalogLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

class AppCsePrintTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherPlan = app(AppCseService::class)->create($other['school'], 2026, $other['user']);
        $fx = SobFixture::make(['slug' => uniqid('cse')]);
        $this->actingAs($fx['user']);

        return [$fx, app(AppCseService::class)->create($fx['school'], 2026, $fx['user']), $otherPlan];
    }

    private function service(): AppCseService
    {
        return app(AppCseService::class);
    }

    private function priced(): CseCatalogItem
    {
        return CseCatalogItem::where('catalog_year', 2026)->whereNotNull('price')->orderBy('sort_order')->firstOrFail();
    }

    private function html(AppCsePlan $plan): string
    {
        return $this->get(route('planning.app-cse.print', $plan))->assertOk()->getContent();
    }

    /** The plan of the totals test: one catalog item (139.36) with 2 + 1 units and one own item (100) with 5 + 2 + 1 units, budget 5,000. */
    private function filled(array $fx, AppCsePlan $plan): AppCsePlan
    {
        $part1 = $this->service()->addCatalogItem($plan, $this->priced()->id);
        $this->service()->updateItem($part1, ['m1' => 2, 'm4' => 1]);
        $part2 = $this->service()->addCustomItem($plan, ['category' => 'Electrical supplies', 'description' => 'Extension wire', 'unit' => 'roll', 'unit_price' => 100]);
        $this->service()->updateItem($part2, ['m2' => 5, 'm5' => 2, 'm8' => 1]);
        $this->service()->updateHeader($plan, ['approved_budget' => 5000, 'approved_budget_words' => 'Five thousand pesos only', 'date_prepared' => '2026-10-11']);

        return $plan->fresh();
    }

    public function test_the_print_has_the_forms_wording_and_the_print_engine_hooks(): void
    {
        [$fx, $plan] = $this->context();
        $this->service()->updateHeader($plan, ['office_name' => 'DEPED- COTABATO (LUBAS ELEMENTARY SCHOOL)', 'account_code' => 'F001', 'contact_person' => 'CHIQUETO E. DOMINGO', 'contact_email' => 'head@example.com', 'contact_phone' => '09170000000']);

        $html = $this->html($plan);

        foreach ([
            'APP-CSE 2020 FORM - 10 September 2019', 'ANNUAL PROCUREMENT PLAN-COMMON SUPPLIES AND EQUIPMENT (APP-CSE) 2026 FORM',
            'Department/Bureau/Office:', 'DEPED- COTABATO (LUBAS ELEMENTARY SCHOOL)', 'Region:', 'Address:', 'Agency Account Code:', 'F001', 'Organization Type:',
            'National Government Agency (NGA)', 'Contact Person:', 'CHIQUETO E. DOMINGO', 'Position:', 'E-mail', 'head@example.com', 'Telephone/Mobile Nos:', '09170000000',
            'Item &amp; Specifications', 'Unit of Measure', 'Monthly Quantity Requirement', 'Total Quantity', 'for the year', 'Price Catalogue', 'Total Amount',
            'Jan', 'Feb', 'Mar', 'April', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec', 'Q1', 'Q1 AMOUNT', 'Q2 AMOUNT', 'Q3', 'Q3 AMOUNT', 'Q4', 'Q4 AMOUNT',
            'PART I. AVAILABLE AT PROCUREMENT SERVICE STORES', 'PART II. OTHER ITEMS NOT AVALABLE AT PS BUT REGULARLY PURCHASED FROM OTHER SOURCES (Note: Please indicate price of items)',
            'A. TOTAL', 'B. ADDITIONAL PROVISION FOR INFLATION', '(10% of TOTAL)', 'C. ADDITIONAL PROVISION FOR TRANSPORT AND FREIGHT COST', 'D. GRAND TOTAL (A + B+ C)',
            'E. APPROVED BUDGET BY THE AGENCY HEAD', 'In Figures and Words:', 'F. MONTHLY CASH REQUIREMENTS', 'G.1 Available at Procurement Service Stores',
            'G.2 Other Items not available at PS but regulary purchased from other sources', 'TOTAL MONTHLY CASH REQUIREMENTS',
            '*Agency must put the monthly requirement for air tickets both local and international.', 'We hereby warrant that the total amount reflected in this Annual Supplies/ Equipment Procurement Plan',
            'has been included in or is within our approved budget for the year.', 'Prepared by:', 'NOTED:', 'Certified Funds Available / Certified Appropriate Funds Available:', 'Approved by:',
            'Property/Supply Officer', 'School Head', 'Accountant / Local Budget Officer', 'Head of Office/Agency', 'Date Prepared:',
        ] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringContainsString('data-official-page', $html);
        $this->assertStringContainsString('data-orientation="landscape"', $html);
        $this->assertStringContainsString('data-paper="longbond"', $html);
        $this->assertStringContainsString('<thead', $html);
        $this->assertStringNotContainsString('<img', $html, 'the Procurement Service form has no logos');
    }

    public function test_every_catalog_row_is_printed_with_zeros_where_unused(): void
    {
        [$fx, $plan] = $this->context();
        $priced = $this->priced();
        $item = $this->service()->addCatalogItem($plan, $priced->id);
        $this->service()->updateItem($item, ['m1' => 2, 'm4' => 1]);

        $html = $this->html($plan);

        $this->assertSame(368, substr_count($html, 'class="catalog-row"'));
        $this->assertStringContainsString($priced->code, $html);
        $this->assertStringContainsString('Pesticides or Pest Repellents', $html);
        $this->assertStringContainsString('Motor Vehicles', $html, 'the group heading above sub-categories A to H');
        $row = $this->rowContaining($html, $priced->code);
        // Q1 2 units = 278.72; Q2 1 unit = 139.36; total 3 units = 418.08 at the catalog price 139.36.
        foreach (['278.72', '139.36', '418.08'] as $figure) {
            $this->assertStringContainsString($figure, $row, $figure);
        }
        $unused = $this->rowContaining($html, CseCatalogItem::where('catalog_year', 2026)->where('sort_order', 2)->value('code'));
        $this->assertStringContainsString('>0<', $unused);
        $this->assertStringContainsString('>0.00<', $unused);
    }

    public function test_part_ii_items_are_grouped_with_a_sub_total_and_escaped(): void
    {
        [$fx, $plan] = $this->context();
        $a = $this->service()->addCustomItem($plan, ['category' => '<b>Electrical</b>', 'description' => '"Quoted" <i>wire</i>', 'unit' => 'roll', 'unit_price' => 10]);
        $b = $this->service()->addCustomItem($plan, ['category' => '<b>Electrical</b>', 'description' => 'Switch', 'unit' => 'pc', 'unit_price' => 20]);
        $c = $this->service()->addCustomItem($plan, ['category' => 'Hardware', 'description' => 'Nails', 'unit' => 'kg', 'unit_price' => 5]);
        $this->service()->updateItem($a, ['m1' => 2]);
        $this->service()->updateItem($b, ['m1' => 1]);
        $this->service()->updateItem($c, ['m2' => 4]);

        $html = $this->html($plan);

        $this->assertStringContainsString('&lt;b&gt;Electrical&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Electrical</b>', $html);
        $this->assertStringNotContainsString('<i>wire</i>', $html);
        $this->assertSame(2, substr_count($html, 'class="sub-total"'), 'one sub total per Part II category');
        $electrical = $this->rowContaining($html, 'Switch');
        $this->assertStringContainsString('20.00', $electrical);
        $subTotals = $this->rowsOfClass($html, 'sub-total');
        $this->assertStringContainsString('40.00', $subTotals[0], 'Electrical: 2 x 10 + 1 x 20');
        $this->assertStringContainsString('20.00', $subTotals[1], 'Hardware: 4 x 5');
    }

    public function test_the_totals_block_matches_the_service(): void
    {
        [$fx, $plan] = $this->context();
        $html = $this->html($this->filled($fx, $plan));

        $text = strip_tags($html);
        $text = (string) preg_replace('/\s+/', ' ', $text);
        foreach (['A. TOTAL 1,218.08', 'B. ADDITIONAL PROVISION FOR INFLATION (10% of TOTAL) 121.81', 'TRANSPORT AND FREIGHT COST 60.90', 'D. GRAND TOTAL (A + B+ C) 1,400.79', 'Five thousand pesos only'] as $expected) {
            $this->assertStringContainsString($expected, $text, $expected);
        }
        $this->assertStringContainsString('5,000.00', $html);
        $g1 = $this->rowContaining($html, 'G.1 Available at Procurement Service Stores');
        $g2 = $this->rowContaining($html, 'G.2 Other Items not available at PS');
        $total = $this->rowContaining($html, 'TOTAL MONTHLY CASH REQUIREMENTS');
        foreach (['278.72', '139.36', '418.08'] as $figure) {
            $this->assertStringContainsString($figure, $g1, $figure);
        }
        foreach (['500.00', '200.00', '100.00', '800.00'] as $figure) {
            $this->assertStringContainsString($figure, $g2, $figure);
        }
        foreach (['778.72', '339.36', '1,218.08'] as $figure) {
            $this->assertStringContainsString($figure, $total, $figure);
        }
    }

    public function test_the_signature_block_prints_the_chosen_names_and_the_typed_head(): void
    {
        [$fx, $plan] = $this->context();
        $pat = SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Pat Supply', 'position' => 'Teacher III']);
        SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Sam Head', 'position' => 'School Head']);
        SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Ann Accountant', 'document_role' => 'Accountant']);
        $this->service()->updateHeader($plan, ['agency_head_name' => 'ROMELITO G. FLORES, CESO V', 'agency_head_position' => 'Schools Division Superintendent', 'date_prepared' => '2026-10-11', 'signatories' => ['prepared_by' => (string) $pat->id]]);

        $html = $this->html($plan->fresh());

        foreach (['Pat Supply', 'Sam Head', 'Ann Accountant', 'ROMELITO G. FLORES, CESO V', 'Schools Division Superintendent', 'October 11, 2026'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        foreach (['Property/Supply Officer', 'School Head', 'Accountant / Local Budget Officer'] as $caption) {
            $this->assertStringContainsString($caption, $html, 'the role captions of the form stay fixed: '.$caption);
        }
    }

    public function test_a_plan_prints_from_its_own_catalog_year_and_price_snapshots(): void
    {
        [$fx, $plan] = $this->context();
        $priced = $this->priced();
        $item = $this->service()->addCatalogItem($plan, $priced->id);
        $this->service()->updateItem($item, ['m1' => 1]);
        $this->assertSame('139.36', (string) $item->unit_price);

        // A newer catalog year is loaded and the 2026 catalog price of that item changes afterwards.
        app(CseCatalogLoader::class)->load([['group_heading' => null, 'category' => 'New', 'code' => 'NEW-2030-1', 'description' => 'A 2030 item', 'unit' => 'pc', 'price' => 1, 'sort_order' => 1]], 2030);
        CseCatalogItem::whereKey($priced->id)->update(['price' => 999.99]);

        $html = $this->html($plan->fresh());

        $this->assertSame(368, substr_count($html, 'class="catalog-row"'));
        $this->assertStringNotContainsString('NEW-2030-1', $html);
        $row = $this->rowContaining($html, $priced->code);
        $this->assertStringContainsString('139.36', $row, 'the plan keeps its own price');
        $this->assertStringNotContainsString('999.99', $row);
    }

    public function test_a_viewer_can_print_and_another_organization_cannot(): void
    {
        [$fx, $plan, $otherPlan] = $this->context();
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);

        $this->actingAs($viewer)->get(route('planning.app-cse.print', $plan))->assertOk();
        $this->assertContains($this->get(route('planning.app-cse.print', $otherPlan))->getStatusCode(), [403, 404]);
        $this->actingAs($fx['user']);
        $this->assertContains($this->get(route('planning.app-cse.print', $otherPlan))->getStatusCode(), [403, 404]);
    }

    public function test_the_plan_page_links_to_the_print(): void
    {
        [, $plan] = $this->context();

        $this->get(route('planning.app-cse.show', $plan))->assertOk()->assertSee(route('planning.app-cse.print', $plan), false);
    }

    /** The first <tr>...</tr> that contains $needle. */
    private function rowContaining(string $html, string $needle): string
    {
        $position = strpos($html, $needle);
        $this->assertNotFalse($position, "'{$needle}' is not printed");
        $start = strrpos(substr($html, 0, $position), '<tr');
        $end = strpos($html, '</tr>', $position);

        return substr($html, $start, $end - $start + 5);
    }

    /** @return array<int, string> every <tr class="$class"> row */
    private function rowsOfClass(string $html, string $class): array
    {
        preg_match_all('/<tr class="'.preg_quote($class, '/').'".*?<\/tr>/s', $html, $matches);

        return $matches[0];
    }
}
