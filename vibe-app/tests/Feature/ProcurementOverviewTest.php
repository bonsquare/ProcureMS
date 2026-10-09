<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_shows_portfolio_metrics_attention_work_and_recent_activity(): void
    {
        [$organization, $school, $user] = $this->tenant();
        $this->request($organization, $school, $user, 'PR-ATTENTION', 'Needs approval', 'pending_approval');
        $this->request($organization, $school, $user, 'PR-DONE', 'Delivered supplies', 'completed');

        $this->actingAs($user)->get(route('procurement'))
            ->assertOk()
            ->assertSee('Procurement overview')
            ->assertSee('Create Purchase Request')->assertDontSee('class="hidden sm:inline">Create Purchase Request', false)
            ->assertSee('Pending approval')
            ->assertSee('Needs approval')
            ->assertSee('Recent activity')
            ->assertSee($school->name);
    }

    public function test_request_registry_filters_by_search_and_status_and_links_to_workspace(): void
    {
        [$organization, $school, $user] = $this->tenant();
        $match = $this->request($organization, $school, $user, 'PR-MATCH', 'Science kits', 'pending_approval');
        $this->request($organization, $school, $user, 'PR-OTHER', 'Office chairs', 'completed');

        $this->actingAs($user)->get(route('procurement.requests', ['search' => 'Science', 'status' => 'pending_approval']))
            ->assertOk()
            ->assertSee('PR-MATCH')
            ->assertDontSee('PR-OTHER')
            ->assertSee('Approval')
            ->assertSee('Open workspace')
            ->assertSee(route('procurement.show', $match));
    }

    public function test_request_registry_is_paginated_and_distinguishes_no_filter_matches(): void
    {
        [$organization, $school, $user] = $this->tenant();
        foreach (range(1, 25) as $index) {
            $this->request($organization, $school, $user, sprintf('PR-%03d', $index), "Request $index", 'draft');
        }

        $this->actingAs($user)->get(route('procurement.requests'))
            ->assertViewHas('procurementRequests', fn ($records) => $records->count() === 20 && $records->total() === 25);

        $this->actingAs($user)->get(route('procurement.requests', ['search' => 'does-not-exist']))
            ->assertOk()
            ->assertSee('No requests match these filters');
    }

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Alpha Organization', 'slug' => 'alpha-overview', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ALPHA-OV', 'name' => 'Alpha School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);

        return [$organization, $school, $user];
    }

    private function request(Organization $organization, School $school, User $user, string $number, string $title, string $status): ProcurementRequest
    {
        return ProcurementRequest::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id,
            'request_number' => $number, 'title' => $title, 'amount' => 1000, 'status' => $status, 'requested_at' => now(),
        ]);
    }
}
