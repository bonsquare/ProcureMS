<?php

namespace Tests\Feature;

use App\Models\PowPlan;
use App\Models\SchoolStaff;
use App\Models\SobPlan;
use App\Services\PowService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** A Program of Works is built from the SOB items of one program and activity, and keeps its own copy. */
class PowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pow(): PowService
    {
        return app(PowService::class);
    }

    /** @return array{0: array<string, mixed>, 1: SobPlan} an SOB with two activities: Activity 1 has two items and a labor line, Activity 2 has one item */
    private function sobWithItems(): array
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $line = fn (string $name, float $frequency, float $quantity, float $cost) => ['particulars' => $name, 'frequency' => $frequency, 'quantity' => $quantity, 'unit' => 'pcs', 'unit_cost' => $cost];
        app(SobService::class)->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [$line('Cement', 1, 6, 280), $line('Rope', 3, 1.5, 100.10)], 'POW required', [['particulars' => '35% Labor', 'unit' => '', 'amount' => 30000]]);
        app(SobService::class)->addItems($plan, $fx['activities'][1]->id, $fx['account']->id, [$line('Paint', 1, 2, 500)]);

        return [$fx, $plan->fresh()];
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function data(array $lines, array $over = []): array
    {
        return $over + ['program' => 'Program One', 'activity_id' => null, 'project_title' => 'Gym renovation', 'lines' => $lines, 'labor_cost' => 0];
    }

    private function line(string $description = 'Cement', float $quantity = 1, float $cost = 10): array
    {
        return ['description' => $description, 'unit' => 'pcs', 'quantity' => $quantity, 'unit_cost' => $cost, 'sob_item_id' => null];
    }

    public function test_materials_come_from_the_chosen_program_and_activity_only(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $one = $this->pow()->materials($plan, 'Program One', $fx['activities'][0]->id);
        $all = $this->pow()->materials($plan, 'Program One', null);

        $this->assertSame(['Cement', 'Rope'], $one->pluck('description')->all());
        $this->assertSame(['Cement', 'Rope', 'Paint'], $all->pluck('description')->all());
        $this->assertSame([], $this->pow()->materials($plan, 'No such program', null)->all());
        $this->assertSame(['Program One'], $this->pow()->programs($plan)->all());
        $this->assertSame([$fx['activities'][0]->id, $fx['activities'][1]->id], $this->pow()->activities($plan, 'Program One')->pluck('id')->all());
    }

    public function test_quantity_is_frequency_times_quantity_and_amount_is_exact(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $rope = $this->pow()->materials($plan, 'Program One', $fx['activities'][0]->id)->firstWhere('description', 'Rope');

        $this->assertSame(4.5, (float) $rope['quantity']);
        $this->assertSame(100.10, (float) $rope['unit_cost']);
        $this->assertSame(450.45, (float) $rope['amount']);
    }

    public function test_labor_is_the_sum_of_the_other_lines_of_the_chosen_activity(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $this->assertSame(30000.0, $this->pow()->labor($plan, 'Program One', $fx['activities'][0]->id));
        $this->assertSame(0.0, $this->pow()->labor($plan, 'Program One', $fx['activities'][1]->id));
        $this->assertNotContains('35% Labor', $this->pow()->materials($plan, 'Program One', null)->pluck('description')->all());
    }

    public function test_create_saves_a_snapshot_that_does_not_follow_the_sob(): void
    {
        [$fx, $plan] = $this->sobWithItems();
        $materials = $this->pow()->materials($plan, 'Program One', $fx['activities'][0]->id);
        $this->assertNotNull($materials->firstWhere('description', 'Cement')['sob_item_id']);

        $pow = $this->pow()->create($plan, $this->data($materials->all(), ['activity_id' => $fx['activities'][0]->id, 'labor_cost' => 30000]), $fx['user']);
        $plan->items()->where('particulars', 'Cement')->first()->update(['unit_cost' => 999, 'amount' => 5994]);
        $plan->items()->where('particulars', 'Cement')->delete();

        $saved = $pow->fresh()->items->firstWhere('description', 'Cement');
        $this->assertSame(280.0, (float) $saved->unit_cost);
        $this->assertSame(1680.0, (float) $saved->amount);
        $this->assertNull($saved->sob_item_id);
        $this->assertSame($fx['school']->id, $pow->school_id);
        $this->assertSame('Gym renovation', $pow->project_title);
    }

    public function test_totals_add_materials_and_labor(): void
    {
        [$fx, $plan] = $this->sobWithItems();
        $pow = $this->pow()->create($plan, $this->data([$this->line('A', 8, 6875), $this->line('B', 1, 0)], ['labor_cost' => 19250]), $fx['user']);

        $this->assertSame(['materials' => 55000.0, 'labor' => 19250.0, 'total' => 74250.0], $this->pow()->totals($pow->fresh()));
    }

    public function test_a_pow_with_no_line_and_no_labor_is_refused(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        try {
            $this->pow()->create($plan, $this->data([]), $fx['user']);
            $this->fail('An empty POW was saved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame(0, PowPlan::count());
    }

    public function test_a_pow_with_labor_only_is_allowed(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $pow = $this->pow()->create($plan, $this->data([], ['labor_cost' => 36000]), $fx['user']);

        $this->assertSame(36000.0, $this->pow()->totals($pow->fresh())['total']);
    }

    public function test_quantity_must_be_positive_unit_cost_not_negative_and_start_not_after_completion(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        foreach ([
            [[$this->line('A', 0, 10)], [], 'lines.0.quantity'],
            [[$this->line('A', 1, -1)], [], 'lines.0.unit_cost'],
            [[$this->line('A')], ['date_started' => '2026-09-17', 'date_completed' => '2026-09-07'], 'date_completed'],
        ] as [$lines, $extra, $field]) {
            try {
                $this->pow()->create($plan, $this->data($lines, $extra), $fx['user']);
                $this->fail('Invalid data was saved: '.$field);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
        $this->assertSame(0, PowPlan::count());
    }

    public function test_default_signatories_use_the_school_head_and_the_last_pow_of_the_school(): void
    {
        [$fx, $plan] = $this->sobWithItems();
        SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'BABY JOY I. CARBONELL', 'position' => 'School Head', 'is_active' => true]);

        $first = $this->pow()->defaultSignatories($fx['school']);
        $this->assertSame('BABY JOY I. CARBONELL', $first['prepared_by']['name']);
        $this->assertSame('School Head', $first['prepared_by']['position']);
        $this->assertSame('', $first['recommended_by']['name']);

        $this->pow()->create($plan, $this->data([$this->line()], ['signatories' => ['recommended_by' => ['name' => 'ENGR. MA. NORA DE LEON', 'position' => 'DepEd Project Engineer']]]), $fx['user']);

        $second = $this->pow()->defaultSignatories($fx['school']);
        $this->assertSame('ENGR. MA. NORA DE LEON', $second['recommended_by']['name']);
        $this->assertSame('DepEd Project Engineer', $second['recommended_by']['position']);
        $this->assertSame('', $second['division_superintendent']['name']);
    }
}
