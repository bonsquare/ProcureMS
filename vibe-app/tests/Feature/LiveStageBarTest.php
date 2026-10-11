<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStageBarTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'live', string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        $user = User::factory()->create(['role' => $role, 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-'.strtoupper($slug), 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);

        return [$user, $request];
    }

    private function prepare(ProcurementRequest $request, User $user, string $type): ProcurementDocument
    {
        return ProcurementDocument::create([
            'organization_id' => $request->organization_id, 'procurement_request_id' => $request->id, 'created_by' => $user->id, 'document_type' => $type,
            'document_number' => strtoupper(substr($type, 0, 3)).'-2026-001', 'document_date' => '2026-10-11', 'status' => 'prepared',
        ]);
    }

    public function test_the_endpoint_returns_the_same_stage_data_the_page_is_built_from(): void
    {
        [$user, $request] = $this->tenant();
        $this->prepare($request, $user, 'request_for_quotation');
        $expected = app(ProcurementWorkspaceService::class)->present($request->fresh()->load('documents'));

        $response = $this->actingAs($user)->getJson(route('procurement.workspace', $request))->assertOk();

        $response->assertJsonPath('stage', $expected['current_stage']);
        $this->assertSame($expected['current_stage'], 'canvass');
        $this->assertNotEmpty($response->json('version'));
        $this->assertStringContainsString('Canvass', $response->json('rail_html'));
        $this->assertStringContainsString('aria-current="step"', $response->json('rail_html'));
        $this->assertStringContainsString('civic-stage-rail', $response->json('rail_html'));
    }

    public function test_the_response_is_never_cached(): void
    {
        [$user, $request] = $this->tenant();

        $cache = $this->actingAs($user)->getJson(route('procurement.workspace', $request))->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_the_version_changes_when_a_document_is_prepared_or_the_status_changes(): void
    {
        [$user, $request] = $this->tenant();
        $first = $this->actingAs($user)->getJson(route('procurement.workspace', $request))->json('version');
        $this->assertSame($first, $this->getJson(route('procurement.workspace', $request))->json('version'), 'an unchanged request keeps its version');

        $this->prepare($request, $user, 'request_for_quotation');
        $second = $this->getJson(route('procurement.workspace', $request))->json('version');
        $this->assertNotSame($first, $second);

        $request->update(['status' => 'returned']);
        $this->assertNotSame($second, $this->getJson(route('procurement.workspace', $request))->json('version'));
    }

    public function test_another_organizations_request_and_a_signed_out_visitor_are_refused(): void
    {
        [, $request] = $this->tenant('one');
        [$stranger] = $this->tenant('two');

        $this->getJson(route('procurement.workspace', $request))->assertUnauthorized();

        $status = $this->actingAs($stranger)->getJson(route('procurement.workspace', $request))->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    public function test_a_viewer_who_can_open_the_request_can_follow_it_live(): void
    {
        [$viewer, $request] = $this->tenant('view', 'viewer');

        $this->actingAs($viewer)->getJson(route('procurement.workspace', $request))->assertOk();
    }

    public function test_the_request_page_carries_the_live_hook_with_the_current_version(): void
    {
        [$user, $request] = $this->tenant();
        $version = $this->actingAs($user)->getJson(route('procurement.workspace', $request))->json('version');

        $html = $this->get(route('procurement.show', $request))->assertOk()->getContent();

        $this->assertStringContainsString('data-live-url="'.route('procurement.workspace', $request).'"', $html);
        $this->assertStringContainsString('data-live-version="'.$version.'"', $html);
        $this->assertStringContainsString('id="stage-rail-notice"', $html);
        $this->assertStringContainsString('This request was updated', $html);
    }
}
