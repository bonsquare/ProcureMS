<?php

namespace Tests\Feature;

use App\Models\PowPlan;
use App\Models\SobPlan;
use App\Models\User;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The Form Generator page under the SOB: pick an SOB, a program and an activity, then make a Program of Works from its items. */
class FormGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: SobPlan} an SOB with Cement and Rope under Activity 1, a labor line, and Paint under Activity 2 */
    private function sobWithItems(): array
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $line = fn (string $name, float $quantity, float $cost) => ['particulars' => $name, 'frequency' => 1, 'quantity' => $quantity, 'unit' => 'pcs', 'unit_cost' => $cost];
        app(SobService::class)->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, [$line('Cement', 6, 280), $line('Rope', 2, 50)], null, [['particulars' => '35% Labor', 'unit' => '', 'amount' => 30000]]);
        app(SobService::class)->addItems($plan, $fx['activities'][1]->id, $fx['account']->id, [$line('Paint', 2, 500)]);

        return [$fx, $plan->fresh()];
    }

    /** @return array<string, mixed> a valid POST body for the first activity, with the second line left unchecked */
    private function body(array $fx, SobPlan $plan, array $over = []): array
    {
        return $over + [
            'sob_plan_id' => $plan->id, 'program' => 'Program One', 'activity_id' => $fx['activities'][0]->id, 'project_title' => 'Gym renovation', 'labor_cost' => 30000,
            'lines' => [
                ['include' => '1', 'description' => 'Cement', 'unit' => 'pcs', 'quantity' => 6, 'unit_cost' => 280],
                ['description' => 'Rope', 'unit' => 'pcs', 'quantity' => 2, 'unit_cost' => 50],
            ],
        ];
    }

    public function test_the_generator_lists_programs_and_activities_of_the_chosen_sob_and_the_materials(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $html = $this->get(route('planning.forms', ['sob_plan_id' => $plan->id, 'program' => 'Program One', 'activity_id' => $fx['activities'][0]->id]))->assertOk()->getContent();

        foreach (['Program of Works', 'Program One', 'Activity 1', 'Activity 2', 'Cement', 'Rope', '30000.00'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Paint', $html, 'only the chosen activity');
        $this->assertStringContainsString('name="lines[0][include]"', $html);
    }

    public function test_the_generator_without_a_choice_shows_the_filters_and_the_saved_pows(): void
    {
        [$fx, $plan] = $this->sobWithItems();
        $this->post(route('planning.pow.store'), $this->body($fx, $plan))->assertSessionHasNoErrors();

        $html = $this->get(route('planning.forms', ['sob_plan_id' => $plan->id]))->assertOk()->getContent();

        $this->assertStringContainsString('Gym renovation', $html, 'the list of saved POWs');
        $this->assertStringContainsString('1ST QUARTER', $html);
    }

    public function test_the_sob_panel_links_to_the_form_generator(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $this->get(route('planning', ['school_id' => $fx['school']->id, 'year' => 2026]))->assertOk()->assertSee(e(route('planning.forms', ['school_id' => $fx['school']->id, 'year' => 2026])), false);
    }

    public function test_create_pow_saves_checked_lines_only_and_redirects_to_the_pow_page(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $response = $this->post(route('planning.pow.store'), $this->body($fx, $plan));

        $pow = PowPlan::firstOrFail();
        $response->assertRedirect(route('planning.pow.show', $pow));
        $this->assertSame(['Cement'], $pow->items->pluck('description')->all());
        $this->assertSame('30000.00', $pow->labor_cost);
        $this->assertSame($fx['activities'][0]->id, $pow->aip_activity_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pow_created', 'auditable_id' => $pow->id]);
    }

    public function test_create_pow_needs_planning_manage_and_the_users_own_school(): void
    {
        $stranger = SobFixture::make();
        [$fx, $plan] = $this->sobWithItems();
        $viewer = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->post(route('planning.pow.store'), $this->body($fx, $plan))->assertForbidden();
        $this->actingAs($stranger['user'])->post(route('planning.pow.store'), $this->body($fx, $plan))->assertNotFound();
        $this->assertSame(0, PowPlan::withoutGlobalScopes()->count());
    }

    public function test_a_tampered_activity_is_refused(): void
    {
        $stranger = SobFixture::make();
        [$fx, $plan] = $this->sobWithItems();

        $this->post(route('planning.pow.store'), $this->body($fx, $plan, ['activity_id' => $stranger['activities'][0]->id]))->assertSessionHasErrors('activity_id');
        $this->assertSame(0, PowPlan::count());
    }

    public function test_nothing_to_put_in_the_pow_is_refused(): void
    {
        [$fx, $plan] = $this->sobWithItems();

        $this->post(route('planning.pow.store'), $this->body($fx, $plan, ['labor_cost' => 0, 'lines' => [['description' => 'Cement', 'unit' => 'pcs', 'quantity' => 6, 'unit_cost' => 280]]]))->assertSessionHasErrors('lines');
        $this->assertSame(0, PowPlan::count());
    }
}
