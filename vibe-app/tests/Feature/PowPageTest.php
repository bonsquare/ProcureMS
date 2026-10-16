<?php

namespace Tests\Feature;

use App\Models\PowPlan;
use App\Models\User;
use App\Services\PowService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** A saved Program of Works opens on its own page where it is edited, printed or deleted. */
class PowPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: PowPlan} */
    private function savedPow(): array
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $pow = app(PowService::class)->create($plan, [
            'program' => 'Program One', 'project_title' => 'Gym renovation', 'labor_cost' => 1000,
            'lines' => [['description' => 'Cement', 'unit' => 'bags', 'quantity' => 6, 'unit_cost' => 280], ['description' => 'Sand', 'unit' => 'cubic', 'quantity' => 3, 'unit_cost' => 1300]],
        ], $fx['user']);

        return [$fx, $pow];
    }

    private function body(array $over = []): array
    {
        return $over + [
            'project_title' => 'Gym renovation', 'labor_cost' => 2500,
            'lines' => [
                ['include' => '1', 'description' => 'Cement', 'unit' => 'bags', 'quantity' => 10, 'unit_cost' => 280],
                ['description' => 'Sand', 'unit' => 'cubic', 'quantity' => 3, 'unit_cost' => 1300],
                ['include' => '1', 'description' => 'Pipe', 'unit' => 'pcs', 'quantity' => 4, 'unit_cost' => 100],
                ['description' => '', 'unit' => '', 'quantity' => '', 'unit_cost' => ''],
            ],
        ];
    }

    public function test_a_pow_can_be_edited_and_the_totals_follow(): void
    {
        [$fx, $pow] = $this->savedPow();

        $this->get(route('planning.pow.show', $pow))->assertOk()->assertSee('Gym renovation')->assertSee('Cement');
        $this->put(route('planning.pow.update', $pow), $this->body(['manpower' => '3 carpenters']))->assertRedirect(route('planning.pow.show', $pow))->assertSessionHasNoErrors();

        $pow->refresh();
        $this->assertSame(['Cement', 'Pipe'], $pow->items->pluck('description')->all(), 'unchecked and blank lines are not saved, a new line is added');
        $this->assertSame('3 carpenters', $pow->manpower);
        $this->assertSame(['materials' => 3200.0, 'labor' => 2500.0, 'total' => 5700.0], app(PowService::class)->totals($pow));
        $this->assertDatabaseHas('audit_logs', ['action' => 'pow_updated', 'auditable_id' => $pow->id]);
    }

    public function test_another_schools_pow_is_forbidden_for_show_update_and_delete(): void
    {
        $stranger = SobFixture::make();
        [$fx, $pow] = $this->savedPow();

        $this->actingAs($stranger['user']);
        $this->get(route('planning.pow.show', $pow))->assertNotFound();
        $this->put(route('planning.pow.update', $pow), $this->body())->assertNotFound();
        $this->delete(route('planning.pow.destroy', $pow))->assertNotFound();
        $this->assertSame('Gym renovation', $pow->fresh()->project_title);
    }

    public function test_delete_removes_the_pow_and_its_lines_and_writes_an_audit_entry(): void
    {
        [$fx, $pow] = $this->savedPow();

        $this->delete(route('planning.pow.destroy', $pow))->assertRedirect(route('planning.forms', ['sob_plan_id' => $pow->sob_plan_id]));

        $this->assertSame(0, PowPlan::count());
        $this->assertDatabaseCount('pow_items', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pow_deleted', 'auditable_id' => $pow->id]);
    }

    public function test_view_only_users_can_open_but_not_change(): void
    {
        [$fx, $pow] = $this->savedPow();
        $viewer = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->get(route('planning.pow.show', $pow))->assertOk();
        $this->put(route('planning.pow.update', $pow), $this->body())->assertForbidden();
        $this->delete(route('planning.pow.destroy', $pow))->assertForbidden();
        $this->assertSame(1, PowPlan::count());
    }
}
