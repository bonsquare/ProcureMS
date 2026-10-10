<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningWithoutSchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_master_sees_an_explanation_instead_of_a_403_when_no_school_exists_yet(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);

        $this->actingAs($master)->get(route('planning'))->assertOk()
            ->assertSee('No school to plan for yet')->assertSee('School Management');
    }

    public function test_a_school_user_without_a_school_is_told_to_ask_the_master(): void
    {
        $user = User::factory()->create(['role' => 'school_admin', 'status' => 'active', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($user)->get(route('planning'))->assertOk()
            ->assertSee('No school to plan for yet')->assertSee('master user');
    }
}
