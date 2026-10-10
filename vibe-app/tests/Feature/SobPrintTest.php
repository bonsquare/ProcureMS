<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\SipPlan;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

class SobPrintTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $fx, string $particulars = 'Bond paper'): SobPlan
    {
        SipPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'start_year' => 2026, 'prepared_by_name' => 'Ana Head', 'prepared_by_position' => 'School Principal I', 'recommended_by_name' => 'Ben Chief', 'recommended_by_position' => 'Chief, SGOD', 'approved_by_name' => 'Cy Super', 'approved_by_position' => 'Schools Division Superintendent']);
        $service = app(SobService::class);
        $plan = $service->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $electric = ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
        $base = ['chart_of_account_id' => $fx['account']->id, 'frequency' => 1, 'unit' => 'ream'];
        $service->addItem($plan, $base + ['aip_activity_id' => $fx['activities'][0]->id, 'particulars' => $particulars, 'quantity' => 10, 'unit_cost' => 100]);
        $service->addItem($plan, ['chart_of_account_id' => $electric->id, 'aip_activity_id' => $fx['activities'][1]->id, 'particulars' => 'Electricity', 'frequency' => 3, 'quantity' => 1, 'unit' => 'month', 'unit_cost' => 150]);

        return $plan->fresh();
    }

    public function test_the_print_page_uses_the_shared_toolbar_and_shows_the_template_parts(): void
    {
        $fx = SobFixture::make();
        $plan = $this->plan($fx);
        $this->actingAs($fx['user']);

        $this->get(route('planning.sob.print', $plan))->assertOk()
            ->assertSee('role="toolbar"', false)->assertSee('class="official-toolbar no-print"', false)->assertSee('Paper Size')->assertSee('Print / Save as PDF')
            ->assertSee('data-official-page', false)->assertSee('data-doc="sob"', false)->assertSee('data-paper="legal"', false)->assertSee('data-orientation="portrait"', false)
            ->assertSee('SCHOOL OPERATING BUDGET')->assertSee('FY 2026 SCHOOL MAINTENANCE AND OTHER OPERATING EXPENSES (MOOE)')->assertSee('1ST QUARTER')
            ->assertSee('PPAs/Expenditures/Item')->assertSee('Particulars')->assertSee('Frequency')->assertSee('Quantity')->assertSee('Unit of Measure')->assertSee('Unit Cost')->assertSee('Amount')
            ->assertSee('ACCESS')->assertSee('Program One')->assertSee('Activity 1')->assertSee('Sub-Total')->assertSee('GRAND TOTAL')->assertSee('Summary per object of expenditure')
            ->assertSee('Prepared by')->assertSee('Recommending Approval')->assertSee('APPROVED:')->assertSee('ANA HEAD')->assertSee('BEN CHIEF')->assertSee('CY SUPER')
            ->assertSee('School Principal I')->assertSee('Schools Division Superintendent');
    }

    public function test_totals_and_summary_agree_with_the_items(): void
    {
        $fx = SobFixture::make();
        $plan = $this->plan($fx);
        $this->actingAs($fx['user']);

        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();

        // 10 x 100 = 1,000 and 3 x 1 x 150 = 450; the grand total is 1,450 in the table and in the summary.
        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('450.00', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, '1,450.00'));
        $this->assertStringContainsString('Office Supplies Expenses', $html);
        $this->assertStringContainsString('Electricity Expenses', $html);
        $this->assertStringContainsString('5-02-03-010', $html);
    }

    public function test_print_is_scoped_to_the_school_and_escapes_text(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $plan = $this->plan($fx, '<script>alert(1)</script>');

        $this->actingAs($other['user'])->get(route('planning.sob.print', $plan))->assertNotFound();

        $html = $this->actingAs($fx['user'])->get(route('planning.sob.print', $plan))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_the_sob_page_links_to_the_print_page(): void
    {
        $fx = SobFixture::make();
        $plan = $this->plan($fx);
        $this->actingAs($fx['user']);

        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee(route('planning.sob.print', $plan), false)->assertSee('Print');
    }
}
