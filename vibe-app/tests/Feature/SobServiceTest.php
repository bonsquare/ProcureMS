<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\ProcurementRequest;
use App\Models\SipPlan;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

class SobServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    private function draft(array $fx, int $quarter = 1, string $fund = 'MOOE'): SobPlan
    {
        return $this->service()->create($fx['school'], 2026, $quarter, $fund, $fx['user']);
    }

    private function line(array $fx, int $activity = 0, array $override = []): array
    {
        return $override + ['aip_activity_id' => $fx['activities'][$activity]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100];
    }

    private function fails(callable $call, ?string $key = null): ValidationException
    {
        try {
            $call();
        } catch (ValidationException $e) {
            if ($key !== null) {
                $this->assertArrayHasKey($key, $e->errors());
            }

            return $e;
        }
        $this->fail('Expected a ValidationException.');
    }

    public function test_create_needs_an_approved_aip_and_an_open_year_and_is_unique(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);

        $this->assertSame('draft', $plan->status);
        $this->assertSame($fx['aip']->id, $plan->aip_id);
        $this->assertNotNull($plan->master_transaction_id);
        $this->assertSame(1, $plan->quarter);
        $this->fails(fn () => $this->draft($fx), 'planning');
        $this->draft($fx, 2);
        $this->draft($fx, 1, 'SEF');
        $this->fails(fn () => $this->draft($fx, 5), 'planning');

        $draftAip = SobFixture::make(['status' => 'draft']);
        $this->fails(fn () => $this->draft($draftAip), 'planning');

        $closed = SobFixture::make();
        FiscalYear::create(['organization_id' => $closed['organization']->id, 'year' => 2026, 'status' => 'closed']);
        $this->fails(fn () => $this->draft($closed));
        $this->assertSame(0, SobPlan::withoutGlobalScopes()->where('school_id', $closed['school']->id)->count());
    }

    public function test_create_copies_the_default_signatories_from_the_sip(): void
    {
        $fx = SobFixture::make();
        SipPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'start_year' => 2026, 'prepared_by_name' => 'Ana Head', 'prepared_by_position' => 'School Head', 'recommended_by_name' => 'Ben Chief', 'recommended_by_position' => 'Chief', 'approved_by_name' => 'Cy Super', 'approved_by_position' => 'Superintendent']);

        $plan = $this->draft($fx);

        $this->assertSame(['Ana Head', 'School Head', 'Ben Chief', 'Chief', 'Cy Super', 'Superintendent'], [$plan->prepared_by_name, $plan->prepared_by_position, $plan->recommended_by_name, $plan->recommended_by_position, $plan->approved_by_name, $plan->approved_by_position]);
    }

    public function test_amount_is_frequency_times_quantity_times_cost_rounded_and_never_taken_from_the_request(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);

        $item = $this->service()->addItem($plan, $this->line($fx, 0, ['frequency' => 1.5, 'quantity' => 2, 'unit_cost' => 10.01, 'amount' => 999999]));

        $this->assertEquals(30.03, $item->fresh()->amount);
        $this->assertSame(30.03, $plan->fresh()->total());
        $default = $this->service()->addItem($plan, ['frequency' => null] + $this->line($fx, 0, ['quantity' => 3, 'unit_cost' => 5]));
        $this->assertEquals(15.00, $default->fresh()->amount);
        $this->assertEquals(1, $default->fresh()->frequency);
    }

    public function test_invalid_numbers_are_refused(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);

        foreach ([['frequency' => 0], ['frequency' => -1], ['frequency' => 'abc'], ['quantity' => 0], ['quantity' => -2], ['quantity' => 'x'], ['unit_cost' => -1], ['unit_cost' => 'free'], ['particulars' => ''], ['unit' => '']] as $bad) {
            $this->fails(fn () => $this->service()->addItem($plan, $this->line($fx, 0, $bad)));
        }
        $this->assertSame(0, SobItem::withoutGlobalScopes()->count());
        $this->service()->addItem($plan, $this->line($fx, 0, ['unit_cost' => 0]));
        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());
    }

    public function test_items_are_refused_for_another_schools_activity_or_organizations_account(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $plan = $this->draft($fx);

        $this->fails(fn () => $this->service()->addItem($plan, $this->line($fx, 0, ['aip_activity_id' => $other['activities'][0]->id])), 'aip_activity_id');
        $this->fails(fn () => $this->service()->addItem($plan, $this->line($fx, 0, ['chart_of_account_id' => $other['account']->id])), 'chart_of_account_id');
        $this->assertSame(0, SobItem::withoutGlobalScopes()->count());
    }

    public function test_over_aip_is_reported_and_does_not_block(): void
    {
        $fx = SobFixture::make(['quarterAmount' => 10000]);
        $plan = $this->draft($fx);

        $this->service()->addItem($plan, $this->line($fx, 0, ['quantity' => 120]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 50]));

        $over = $this->service()->overAip($plan->fresh());
        $this->assertCount(1, $over);
        $this->assertSame($fx['activities'][0]->id, $over[0]['aip_activity_id']);
        $this->assertSame(12000.0, $over[0]['sob']);
        $this->assertSame(10000.0, $over[0]['aip']);
        $this->assertSame(2, SobItem::withoutGlobalScopes()->count());
    }

    public function test_summary_by_account_and_grouping_totals(): void
    {
        $fx = SobFixture::make();
        $second = ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
        $plan = $this->draft($fx);
        $this->service()->addItem($plan, $this->line($fx, 0, ['quantity' => 10, 'unit_cost' => 100]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 5, 'unit_cost' => 100, 'chart_of_account_id' => $second->id]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 2, 'unit_cost' => 100]));

        $summary = $this->service()->summaryByAccount($plan->fresh());
        $this->assertSame(['5-02-03-010', '5-02-12-020'], $summary->pluck('code')->all());
        $this->assertSame([1200.0, 500.0], $summary->pluck('total')->all());

        $grouped = $this->service()->grouped($plan->fresh());
        $this->assertSame('Access', $grouped[0]['pillar']);
        $this->assertSame(1700.0, $grouped[0]['total']);
        $this->assertSame('Program One', $grouped[0]['programs'][0]['program']);
        $this->assertSame(1700.0, $grouped[0]['programs'][0]['total']);
        $activities = $grouped[0]['programs'][0]['activities'];
        $this->assertSame([1000.0, 700.0], array_column($activities, 'total'));
        $this->assertSame(10000.0, $activities[0]['aip_amount']);
        $this->assertCount(2, $activities[1]['items']);
    }

    public function test_edit_and_delete_only_while_draft(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        $item = $this->service()->addItem($plan, $this->line($fx));

        $updated = $this->service()->updateItem($item, $this->line($fx, 1, ['quantity' => 4, 'unit_cost' => 25]));
        $this->assertEquals(100.0, $updated->fresh()->amount);
        $this->assertSame($fx['activities'][1]->id, $updated->fresh()->aip_activity_id);
        $header = $this->service()->updateHeader($plan, ['fund_source' => 'SEF', 'prepared_by_name' => 'New Head']);
        $this->assertSame(['SEF', 'New Head'], [$header->fund_source, $header->prepared_by_name]);

        $this->service()->approve($plan->fresh(), $fx['user']);
        $locked = $plan->fresh();
        $this->fails(fn () => $this->service()->updateItem($item->fresh(), $this->line($fx)), 'planning');
        $this->fails(fn () => $this->service()->deleteItem($item->fresh()), 'planning');
        $this->fails(fn () => $this->service()->addItem($locked, $this->line($fx)), 'planning');
        $this->fails(fn () => $this->service()->updateHeader($locked, ['fund_source' => 'MOOE']), 'planning');
        $this->fails(fn () => $this->service()->deletePlan($locked), 'planning');

        $other = $this->draft($fx, 2);
        $extra = $this->service()->addItem($other, $this->line($fx));
        $this->service()->deleteItem($extra);
        $this->service()->deletePlan($other);
        $this->assertNull(SobPlan::withoutGlobalScopes()->find($other->id));
    }

    public function test_approve_creates_allotments_per_fund_account_and_program_with_the_quarter_amount(): void
    {
        $fx = SobFixture::make();
        $electric = ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
        $plan = $this->draft($fx);
        $this->service()->addItem($plan, $this->line($fx, 0, ['quantity' => 10, 'unit_cost' => 100]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 5, 'unit_cost' => 100]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 3, 'unit_cost' => 100, 'chart_of_account_id' => $electric->id]));

        $result = $this->service()->approve($plan->fresh(), $fx['user']);

        $this->assertSame(['created' => 2, 'updated' => 0], $result);
        $lines = BudgetAllocation::withoutGlobalScopes()->orderBy('uacs_code')->get();
        $this->assertCount(2, $lines);
        $supplies = $lines[0];
        $this->assertSame(['5-02-03-010', 'MOOE', 'Program One', 2026], [$supplies->uacs_code, $supplies->source_of_fund, $supplies->program, (int) $supplies->fiscal_year]);
        $this->assertEquals([1500, 0, 0, 0, 1500], [$supplies->q1_amount, $supplies->q2_amount, $supplies->q3_amount, $supplies->q4_amount, $supplies->amount]);
        $this->assertEquals(300, $lines[1]->amount);
        $this->assertSame([$fx['aip']->id, $fx['school']->id, $fx['school']->name], [$supplies->aip_id, $supplies->school_id, $supplies->office]);
        $this->assertStringStartsWith('BA-', $supplies->budget_ref_no);
        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->approved_at);
    }

    public function test_q1_then_q2_fill_one_line_and_amount_is_the_sum(): void
    {
        $fx = SobFixture::make();
        $q1 = $this->draft($fx, 1);
        $this->service()->addItem($q1, $this->line($fx, 0, ['quantity' => 10, 'unit_cost' => 100]));
        $q2 = $this->draft($fx, 2);
        $this->service()->addItem($q2, $this->line($fx, 0, ['quantity' => 5, 'unit_cost' => 100]));

        $this->service()->approve($q1->fresh(), $fx['user']);
        $result = $this->service()->approve($q2->fresh(), $fx['user']);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $line = BudgetAllocation::withoutGlobalScopes()->sole();
        $this->assertEquals([1000, 500, 0, 0, 1500], [$line->q1_amount, $line->q2_amount, $line->q3_amount, $line->q4_amount, $line->amount]);
    }

    public function test_approve_refuses_below_the_obligated_amount_and_rolls_back_everything(): void
    {
        $fx = SobFixture::make();
        $electric = ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
        // A line that already exists (for example from an earlier approval) and has been obligated more than this SOB would leave allotted.
        $existing = BudgetAllocation::create([
            'organization_id' => $fx['organization']->id, 'aip_id' => $fx['aip']->id, 'school_id' => $fx['school']->id, 'office' => 'x', 'fiscal_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'source_of_fund' => 'MOOE', 'program' => 'Program One', 'chart_of_account_id' => $electric->id, 'uacs_code' => $electric->code, 'particulars' => $electric->title,
            'amount' => 2000, 'q1_amount' => 2000, 'q2_amount' => 0, 'q3_amount' => 0, 'q4_amount' => 0, 'budget_ref_no' => 'BA-2026-9001', 'created_by' => $fx['user']->id,
        ]);
        ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-OBL', 'title' => 'Bulbs', 'amount' => 1500, 'status' => 'approved', 'budget_allocation_id' => $existing->id]);

        $plan = $this->draft($fx);
        $this->service()->addItem($plan, $this->line($fx, 0, ['quantity' => 10, 'unit_cost' => 100]));
        $this->service()->addItem($plan, $this->line($fx, 1, ['quantity' => 5, 'unit_cost' => 100, 'chart_of_account_id' => $electric->id]));

        $error = $this->fails(fn () => $this->service()->approve($plan->fresh(), $fx['user']), 'planning');

        $this->assertStringContainsString('Program One', $error->errors()['planning'][0]);
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
        $this->assertEquals(2000, $existing->fresh()->amount);
        $this->assertSame('draft', $plan->fresh()->status);
    }

    public function test_approve_twice_is_refused_and_creates_nothing_more(): void
    {
        $fx = SobFixture::make();
        $plan = $this->draft($fx);
        $this->service()->addItem($plan, $this->line($fx));
        $this->service()->approve($plan->fresh(), $fx['user']);

        $this->fails(fn () => $this->service()->approve($plan->fresh(), $fx['user']), 'planning');
        $this->fails(fn () => $this->service()->approve($plan, $fx['user']), 'planning');

        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_approve_needs_an_item_and_an_open_year_and_writes_audit_and_transaction_event(): void
    {
        $fx = SobFixture::make();
        $empty = $this->draft($fx);
        $this->fails(fn () => $this->service()->approve($empty, $fx['user']), 'planning');

        $this->service()->addItem($empty, $this->line($fx));
        FiscalYear::create(['organization_id' => $fx['organization']->id, 'year' => 2026, 'status' => 'closed']);
        $this->fails(fn () => $this->service()->approve($empty->fresh(), $fx['user']));
        $this->assertSame('draft', $empty->fresh()->status);

        FiscalYear::withoutGlobalScopes()->where('organization_id', $fx['organization']->id)->update(['status' => 'open']);
        $this->service()->approve($empty->fresh(), $fx['user']);

        $this->assertDatabaseHas('audit_logs', ['school_id' => $fx['school']->id, 'action' => 'sob_approved', 'auditable_id' => $empty->id]);
        $this->assertTrue($empty->fresh()->transaction->events()->where('module', 'sob')->where('action', 'approved')->exists());
        $this->assertSame(1, AuditLog::where('action', 'sob_approved')->count());
    }
}
