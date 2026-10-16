<?php

namespace Tests\Feature;

use App\Models\PowItem;
use App\Models\PowPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** A Program of Works keeps its own lines, in order, and belongs to one organization. */
class PowModelTest extends TestCase
{
    use RefreshDatabase;

    private function pow(array $fx): PowPlan
    {
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        return PowPlan::create([
            'organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'sob_plan_id' => $plan->id,
            'program' => 'Program One', 'project_title' => 'Program One', 'labor_cost' => 0, 'created_by' => $fx['user']->id,
        ]);
    }

    public function test_a_pow_keeps_its_lines_in_order_and_is_scoped_to_its_organization(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $pow = $this->pow($fx);
        foreach ([2 => 'Second', 1 => 'First'] as $sort => $description) {
            PowItem::create(['organization_id' => $fx['organization']->id, 'pow_plan_id' => $pow->id, 'description' => $description, 'unit' => 'pcs', 'quantity' => 1, 'unit_cost' => 10, 'amount' => 10, 'sort' => $sort]);
        }
        $this->pow($other);

        $this->actingAs($fx['user']);

        $this->assertSame(['First', 'Second'], $pow->fresh()->items->pluck('description')->all());
        $this->assertSame(1, PowPlan::count());
    }
}
