<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** An approved SOB can be reopened for revision, edited and approved again; the budget lines follow every change. */
class SobRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    private function line(array $fx, int $activity = 0, array $override = []): array
    {
        return $override + ['aip_activity_id' => $fx['activities'][$activity]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100];
    }

    /** An approved SOB for the quarter with one line of 10 x 100 = 1,000 (and any extra lines given). */
    private function approved(array $fx, int $quarter = 1, string $fund = 'MOOE', array $extraLines = []): SobPlan
    {
        $plan = $this->service()->create($fx['school'], 2026, $quarter, $fund, $fx['user']);
        $this->service()->addItem($plan, $this->line($fx));
        foreach ($extraLines as $extra) {
            $this->service()->addItem($plan, $extra);
        }
        $this->service()->approve($plan->fresh(), $fx['user']);

        return $plan->fresh();
    }

    private function electric(array $fx): ChartOfAccount
    {
        return ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-12-020', 'title' => 'Electricity Expenses', 'category' => 'Expense']);
    }

    private function allotment(string $fund, ?int $accountId = null): BudgetAllocation
    {
        return BudgetAllocation::withoutGlobalScopes()->where('source_of_fund', $fund)->when($accountId, fn ($query) => $query->where('chart_of_account_id', $accountId))->firstOrFail();
    }

    private function fails(callable $call, string $key = 'planning'): ValidationException
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());

            return $exception;
        }
        $this->fail('Expected a ValidationException.');
    }

    public function test_the_quarter_and_fund_of_a_draft_can_be_changed_and_a_clash_is_refused(): void
    {
        $fx = SobFixture::make();
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->create($fx['school'], 2026, 2, 'MOOE', $fx['user']);
        $this->service()->create($fx['school'], 2026, 1, 'SEF', $fx['user']);

        $moved = $this->service()->updateHeader($plan, ['quarter' => 3, 'fund_source' => 'IGP']);
        $this->assertSame([3, 'IGP'], [$moved->quarter, $moved->fund_source]);

        $this->fails(fn () => $this->service()->updateHeader($moved, ['quarter' => 2, 'fund_source' => 'MOOE']));
        $this->fails(fn () => $this->service()->updateHeader($moved, ['quarter' => 1, 'fund_source' => 'SEF']));
        $this->fails(fn () => $this->service()->updateHeader($moved, ['quarter' => 5]), 'quarter');
        $this->assertSame([3, 'IGP'], [$plan->fresh()->quarter, $plan->fresh()->fund_source]);
    }

    public function test_reopening_makes_the_approved_sob_a_draft_and_keeps_its_budget_lines(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx);

        $reopened = $this->service()->reopen($plan, $fx['user']);

        $this->assertSame('draft', $reopened->status);
        $this->assertNull($reopened->approved_at);
        $this->assertEquals(1000, $this->allotment('MOOE')->amount, 'the budget line stays until the SOB is approved again');
        $this->assertSame(['fund' => 'MOOE', 'quarter' => 1], collect($reopened->allotment_snapshot)->only(['fund', 'quarter'])->all());
        $this->assertCount(1, $reopened->allotment_snapshot['groups']);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'sob_reopened')->where('auditable_id', $plan->id)->exists());
        $this->assertTrue($plan->transaction->events()->where('module', 'sob')->where('action', 'reopened')->exists());

        $this->service()->addItem($reopened, $this->line($fx, 1, ['particulars' => 'Folders']));
        $this->assertSame(2, $reopened->items()->count(), 'everything is editable again');
    }

    public function test_only_an_approved_sob_in_an_open_year_can_be_reopened(): void
    {
        $fx = SobFixture::make();
        $draft = $this->service()->create($fx['school'], 2026, 2, 'MOOE', $fx['user']);
        $this->fails(fn () => $this->service()->reopen($draft, $fx['user']));

        $approved = $this->approved($fx);
        FiscalYear::create(['organization_id' => $fx['organization']->id, 'year' => 2026, 'status' => 'closed']);
        $this->fails(fn () => $this->service()->reopen($approved, $fx['user']), 'fiscal_year');
        $this->assertSame('approved', $approved->fresh()->status);
    }

    private function appItemFor(array $fx, SobPlan $plan, string $appStatus): AppItem
    {
        $appPlan = AppPlan::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'status' => $appStatus]);

        return AppItem::create([
            'organization_id' => $fx['organization']->id, 'app_plan_id' => $appPlan->id, 'sob_item_id' => $plan->items()->first()->id, 'procurement_item' => 'Bond paper',
            'quantity' => 10, 'unit' => 'ream', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 1000, 'fund_source' => 'MOOE',
        ]);
    }

    public function test_reopening_is_refused_when_an_approved_app_or_a_purchase_request_uses_its_items(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx);
        $appItem = $this->appItemFor($fx, $plan, 'approved');
        $this->fails(fn () => $this->service()->reopen($plan, $fx['user']));
        $this->assertSame('approved', $plan->fresh()->status);

        $appItem->plan->update(['status' => 'draft']);
        $request = ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-SOB', 'title' => 'Paper', 'amount' => 500, 'status' => 'submitted']);
        ProcurementRequestItem::create(['organization_id' => $fx['organization']->id, 'procurement_request_id' => $request->id, 'app_item_id' => $appItem->id, 'name' => 'Bond paper', 'quantity' => 5, 'unit' => 'ream', 'unit_price' => 100, 'total' => 500]);

        $error = $this->fails(fn () => $this->service()->reopen($plan, $fx['user']));
        $this->assertStringContainsString('Purchase Request', $error->errors()['planning'][0]);
        $this->assertSame('approved', $plan->fresh()->status);
    }

    public function test_reopening_removes_the_sobs_items_from_a_draft_app(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx);
        $appItem = $this->appItemFor($fx, $plan, 'draft');

        $this->service()->reopen($plan, $fx['user']);

        $this->assertNull(AppItem::withoutGlobalScopes()->find($appItem->id), 'the draft APP no longer keeps a stale copy; generate the APP again after approving');
        $this->assertSame(1, $plan->items()->count(), 'the SOB items themselves are kept');
    }

    public function test_approving_again_updates_a_changed_amount(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx);
        $this->service()->reopen($plan, $fx['user']);
        $this->service()->updateItem($plan->items()->first(), $this->line($fx, 0, ['quantity' => 25]));

        $this->service()->approve($plan->fresh(), $fx['user']);

        $line = $this->allotment('MOOE');
        $this->assertEquals([2500, 2500], [$line->q1_amount, $line->amount]);
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
        $this->assertSame('approved', $plan->fresh()->status);
    }

    public function test_approving_again_zeroes_the_line_of_a_removed_account(): void
    {
        $fx = SobFixture::make();
        $electric = $this->electric($fx);
        $plan = $this->approved($fx, 1, 'MOOE', [$this->line($fx, 1, ['chart_of_account_id' => $electric->id, 'particulars' => 'Bulbs', 'quantity' => 3, 'unit_cost' => 100])]);
        $this->assertEquals(300, $this->allotment('MOOE', $electric->id)->amount);
        $this->service()->reopen($plan, $fx['user']);
        $this->service()->deleteItem($plan->items()->where('chart_of_account_id', $electric->id)->first());

        $result = $this->service()->approve($plan->fresh(), $fx['user']);

        $this->assertEquals([0, 0], [$this->allotment('MOOE', $electric->id)->q1_amount, $this->allotment('MOOE', $electric->id)->amount]);
        $this->assertEquals(1000, $this->allotment('MOOE', $fx['account']->id)->amount, 'the other line is untouched');
        $this->assertSame(['created' => 0, 'updated' => 2], $result, 'the released line counts as updated');
    }

    public function test_approving_again_moves_the_lines_when_the_fund_changes(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx);
        $this->service()->reopen($plan, $fx['user']);
        $this->service()->updateHeader($plan->fresh(), ['fund_source' => 'SEF']);

        $result = $this->service()->approve($plan->fresh(), $fx['user']);

        $this->assertSame(['created' => 1, 'updated' => 1], $result);
        $this->assertEquals(0, $this->allotment('MOOE')->amount, 'the old fund no longer holds the SOB amount');
        $this->assertEquals([1000, 1000], [$this->allotment('SEF')->q1_amount, $this->allotment('SEF')->amount]);
    }

    public function test_approving_again_moves_the_amount_when_the_quarter_changes(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approved($fx, 1);
        $this->service()->reopen($plan, $fx['user']);
        $this->service()->updateHeader($plan->fresh(), ['quarter' => 2]);

        $this->service()->approve($plan->fresh(), $fx['user']);

        $line = $this->allotment('MOOE');
        $this->assertEquals([0, 1000, 1000], [$line->q1_amount, $line->q2_amount, $line->amount]);
        $this->assertSame(1, BudgetAllocation::withoutGlobalScopes()->count());
    }

    public function test_approving_again_refuses_to_release_below_the_obligated_amount_and_changes_nothing(): void
    {
        $fx = SobFixture::make();
        $electric = $this->electric($fx);
        $plan = $this->approved($fx, 1, 'MOOE', [$this->line($fx, 1, ['chart_of_account_id' => $electric->id, 'particulars' => 'Bulbs', 'quantity' => 3, 'unit_cost' => 100])]);
        $line = $this->allotment('MOOE', $electric->id);
        ProcurementRequest::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'requested_by' => $fx['user']->id, 'request_number' => 'PR-2026-OBL2', 'title' => 'Bulbs', 'amount' => 200, 'status' => 'submitted', 'budget_allocation_id' => $line->id]);
        $this->service()->reopen($plan, $fx['user']);
        $this->service()->deleteItem($plan->items()->where('chart_of_account_id', $electric->id)->first());

        $error = $this->fails(fn () => $this->service()->approve($plan->fresh(), $fx['user']));

        $this->assertStringContainsString('obligated', $error->errors()['planning'][0]);
        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertEquals(300, $line->fresh()->amount);
    }

    public function test_a_sob_approved_before_the_snapshot_existed_still_reopens_and_releases(): void
    {
        $fx = SobFixture::make();
        $electric = $this->electric($fx);
        $plan = $this->approved($fx, 1, 'MOOE', [$this->line($fx, 1, ['chart_of_account_id' => $electric->id, 'particulars' => 'Bulbs', 'quantity' => 3, 'unit_cost' => 100])]);
        SobPlan::withoutGlobalScopes()->whereKey($plan->id)->update(['allotment_snapshot' => null]);

        $reopened = $this->service()->reopen($plan->fresh(), $fx['user']);
        $this->assertCount(2, $reopened->allotment_snapshot['groups'], 'the snapshot is rebuilt from the items that were approved');
        $this->service()->deleteItem(SobItem::withoutGlobalScopes()->where('sob_plan_id', $plan->id)->where('chart_of_account_id', $electric->id)->first());
        $this->service()->approve($plan->fresh(), $fx['user']);

        $this->assertEquals(0, $this->allotment('MOOE', $electric->id)->amount);
    }

    /** @return array{used: array<string, array<int, string>>, funds: array<int, string>, quarters: int} what the Create SOB window of the Planning page offers */
    private function createWindow(string $html): array
    {
        $this->assertSame(1, preg_match('/<dialog id="dlg-sob-create" data-used="([^"]*)"(.*?)<\/dialog>/s', $html, $window), 'one Create SOB window');
        preg_match('/<select name="fund_source"[^>]*>(.*?)<\/select>/s', $window[2], $funds);
        preg_match('/<select name="quarter"[^>]*>(.*?)<\/select>/s', $window[2], $quarters);

        return [
            'used' => json_decode(html_entity_decode($window[1]), true),
            'funds' => array_values(array_filter(array_map('trim', explode('|', strip_tags(str_replace('</option>', '|', $funds[1])))))),
            'quarters' => substr_count($quarters[1], '<option'),
        ];
    }

    public function test_the_create_sob_window_offers_the_quarters_and_the_funds_and_knows_which_funds_each_quarter_has(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->create($fx['school'], 2026, 1, 'SEF', $fx['user']);

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $window = $this->createWindow($html);

        $this->assertSame(4, $window['quarters']);
        $this->assertStringNotContainsString('<input name="fund_source"', $html, 'the fund is chosen from a list, not typed');
        $this->assertContains('SBFP', $window['funds']);
        $this->assertContains('MOOE', $window['funds']);
        $this->assertContains('School MOOE', $window['funds'], 'a different fund with a similar name is a different choice');
        $this->assertEqualsCanonicalizing(['MOOE', 'SEF'], $window['used']['1'], 'Q1 already has a MOOE and a SEF SOB, so the window does not offer them for Q1');
        $this->assertArrayNotHasKey('2', $window['used'], 'Q2 has none yet');
        $this->post(route('planning.sob.store'), ['school_id' => $fx['school']->id, 'fiscal_year' => 2026, 'quarter' => 1, 'fund_source' => 'SBFP'])->assertSessionHasNoErrors();
        $this->assertSame(3, SobPlan::where('quarter', 1)->count(), 'several SOBs in one quarter, one per fund');
    }

    public function test_the_planning_page_lists_the_sobs_created_and_each_one_can_be_edited(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $first = $this->service()->create($fx['school'], 2026, 2, 'MOOE', $fx['user']);
        $second = $this->service()->create($fx['school'], 2026, 1, 'SEF', $fx['user']);

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString('History of SOB created', $html);
        $this->assertStringContainsString(route('planning.sob.show', $first), $html);
        $this->assertStringContainsString(route('planning.sob.show', $second), $html);
        $this->assertStringContainsString('data-action="'.route('planning.sob.update', $first).'"', $html, 'a row has its own Edit');
        $this->put(route('planning.sob.update', $first), ['fund_source' => 'SEF', 'quarter' => 3])->assertSessionHasNoErrors();
        $this->assertSame([3, 'SEF'], [$first->fresh()->quarter, $first->fresh()->fund_source]);
    }

    public function test_a_quarter_with_every_fund_used_is_told_so_in_the_create_window(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        foreach (collect(Aip::FUNDS)->flatten()->unique() as $fund) {
            $this->service()->create($fx['school'], 2026, 1, $fund, $fx['user']);
        }

        $html = $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->getContent();
        $window = $this->createWindow($html);

        $this->assertSame(1, substr_count($html, 'Every fund already has an SOB this quarter.'));
        $this->assertEqualsCanonicalizing(collect(Aip::FUNDS)->flatten()->unique()->values()->all(), $window['used']['1'], 'every fund is taken in Q1');
    }

    public function test_the_draft_page_edits_the_fund_and_quarter_from_dropdowns(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'Special Fund', $fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<select name="fund_source"[^>]*>(.*?)<\/select>/s', $html, $fundSelect));
        $this->assertStringContainsString('<option value="Special Fund" selected>', $fundSelect[1], 'a fund that is not in the list stays selectable');
        $this->assertStringContainsString('<option value="MOOE"', $fundSelect[1]);
        $this->assertMatchesRegularExpression('/<select name="quarter"[^>]*>.*?<option value="1" selected>1ST QUARTER<\/option>.*?4TH QUARTER/s', $html);
        $this->put(route('planning.sob.update', $plan), ['fund_source' => 'SEF', 'quarter' => 3])->assertSessionHasNoErrors();
        $this->assertSame(['SEF', 3], [$plan->fresh()->fund_source, $plan->fresh()->quarter]);
        $this->put(route('planning.sob.update', $plan), ['quarter' => 9])->assertSessionHasErrors('quarter');
    }

    public function test_the_details_and_signatories_form_posts_to_the_real_update_route(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $this->get(route('planning.sob.show', $plan))->assertOk()
            ->assertSee('action="'.route('planning.sob.update', $plan).'"', false)
            ->assertDontSee("{ route('planning.sob.update'", false);
    }

    public function test_the_approved_page_offers_reopen_to_managers_only_and_reopening_works(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);

        $this->get(route('planning.sob.show', $plan))->assertOk()->assertSee('Reopen as a draft')->assertSee(route('planning.sob.reopen', $plan), false);
        $viewer = User::factory()->create(['role' => 'viewer', 'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id]);
        $this->actingAs($viewer);
        $this->get(route('planning.sob.show', $plan))->assertOk()->assertDontSee('Reopen as a draft');
        $this->post(route('planning.sob.reopen', $plan))->assertForbidden();
        $this->assertSame('approved', $plan->fresh()->status);

        $this->actingAs($fx['user']);
        $this->post(route('planning.sob.reopen', $plan))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $plan->fresh()->status);
        $this->get(route('planning.sob.show', $plan))->assertOk()->assertDontSee('Reopen as a draft')->assertSee('name="quarter"', false);
    }

    public function test_a_refused_reopen_says_why_and_another_organization_cannot_reopen(): void
    {
        $other = SobFixture::make(['slug' => uniqid('oth')]);
        $otherPlan = $this->approved($other);
        $fx = SobFixture::make(['slug' => uniqid('own')]);
        $this->actingAs($fx['user']);
        $plan = $this->approved($fx);
        $this->appItemFor($fx, $plan, 'approved');

        $this->post(route('planning.sob.reopen', $plan))->assertSessionHasErrors('planning');
        $this->assertSame('approved', $plan->fresh()->status);
        $this->post(route('planning.sob.reopen', $otherPlan))->assertStatus(404);
        $this->assertSame('approved', SobPlan::withoutGlobalScopes()->find($otherPlan->id)->status);
    }

    public function test_the_item_selects_name_the_program_only_and_show_the_complete_account_text(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $activityText = 'Conduct of the quarterly school-based learning recovery sessions in reading and numeracy for Grade 1 to Grade 6 learners with the participation of parents, teachers and the barangay council '.str_repeat('and community partners ', 3);
        $accountTitle = 'Import Duties - Optical, Photographic, Cinematographic, Measuring, Checking, Precision, Medical or Surgical Instruments and Apparatus; Clocks and Watches; Musical Instruments; Parts and Accessories Thereof';
        $fx['activities'][0]->update(['activity' => $activityText]);
        $fx['account']->update(['title' => $accountTitle]);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItem($plan, $this->line($fx));

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        $this->assertStringNotContainsString($activityText, $html, 'the activity is not shown: the SOB goes by the program, so a change to the activity text cannot go out of step');
        $this->assertStringContainsString($accountTitle, $html, 'and the whole account title');
        $this->assertSame(4, preg_match_all('/<p data-full-text-output/', $html), 'a full-text line under each of the 2 selects of the add form and of the one item edit form');
        $this->assertSame(4, preg_match_all('/<div class="[^"]*" data-full-text-box>/', $html));
    }
}
