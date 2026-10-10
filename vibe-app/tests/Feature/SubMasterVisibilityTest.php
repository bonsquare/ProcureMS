<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubMasterVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $code): array
    {
        $organization = Organization::create(['name' => "Org {$code}", 'slug' => "org-{$code}", 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => "School {$code}", 'status' => 'active']);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026]);

        return [$organization, $school, $user];
    }

    public function test_a_sub_master_sees_every_schools_records_and_an_ordinary_user_only_their_own(): void
    {
        [, , $adminA] = $this->tenant('A');
        $this->tenant('B');
        $sub = User::factory()->subMaster()->create();

        $this->actingAs($sub);
        $this->assertSame(2, Aip::count());

        $this->actingAs($adminA);
        $this->assertSame(1, Aip::count());
    }

    public function test_the_main_pages_open_for_a_sub_master_without_a_school(): void
    {
        $this->tenant('A');
        $sub = User::factory()->subMaster()->create();

        foreach (['home', 'budget', 'planning', 'procurement', 'liquidation', 'accounting', 'allotment-registry'] as $name) {
            $this->actingAs($sub)->get(route($name))->assertOk();
        }
    }

    public function test_a_school_user_stays_isolated_on_the_same_pages(): void
    {
        [, , $adminA] = $this->tenant('A');
        [, $schoolB] = $this->tenant('B');

        $this->followingRedirects()->actingAs($adminA)->get(route('aip'))->assertOk()->assertDontSee($schoolB->name);
    }

    public function test_school_user_counts_exclude_both_master_roles(): void
    {
        [, $school] = $this->tenant('A');
        User::where('school_id', $school->id)->delete();
        User::factory()->subMaster()->create(['school_id' => $school->id]);
        User::factory()->create(['role' => 'master_user', 'school_id' => $school->id]);

        $this->assertTrue((new SchoolTakeoverService)->vacantSchools()->contains('id', $school->id));
    }

    public function test_a_sub_master_without_a_school_signs_in_and_reaches_the_dashboard(): void
    {
        $this->tenant('A');
        $sub = User::factory()->subMaster()->create(['email' => 'sub@example.com']);

        $this->post(route('login.store'), ['email' => 'sub@example.com', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($sub);
        $this->get(route('home'))->assertOk();
    }

    public function test_a_sub_master_can_write_without_any_subscription(): void
    {
        $sub = User::factory()->subMaster()->create();

        $this->actingAs($sub)->post(route('procurement.store'), [])->assertSessionHasErrors();
    }

    public function test_a_sub_master_has_no_station_to_transfer_from_like_the_master(): void
    {
        $sub = User::factory()->subMaster()->create();

        $this->actingAs($sub)->get(route('station-transfer'))->assertForbidden();
    }
}
