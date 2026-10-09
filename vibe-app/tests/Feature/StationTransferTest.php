<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StationTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StationTransferTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);

        return [$organization, $school, $user];
    }

    private function vacantSchool(string $slug): School
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function staff(Organization $organization, School $school, string $name, array $extra = []): SchoolStaff
    {
        return SchoolStaff::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => $name, ...$extra]);
    }

    public function test_ended_staff_records_are_hidden_from_normal_queries_but_kept(): void
    {
        [$organization, $school] = $this->tenant('hide-a');
        $this->staff($organization, $school, 'Active Person');
        $ended = $this->staff($organization, $school, 'Former Person');
        $ended->update(['ended_at' => now()]);

        $this->assertSame(['Active Person'], SchoolStaff::pluck('name')->all());
        $this->assertSame(2, SchoolStaff::withoutGlobalScopes()->where('school_id', $school->id)->count());
    }
}
