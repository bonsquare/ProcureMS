<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_users_own_subscription_wins_over_the_schools(): void
    {
        [$organization, $school, $user] = $this->tenant('sub-a');
        $own = Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'enterprise', 'billing_cycle' => 'annual', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDays(40)]);

        $this->assertSame($own->id, $user->activeSubscription()->id);
        $this->assertSame('professional', User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'viewer'])->activeSubscription()->plan, 'a legacy colleague without a plan keeps the school row');
    }

    public function test_registration_records_the_subscription_owner(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Delta Elementary School', 'system_user_given_name' => 'Dina', 'system_user_surname' => 'Cruz', 'system_user_username' => 'dina.cruz',
            'system_user_position' => 'Principal', 'system_user_email' => 'dina@example.com', 'system_user_phone' => '09170000000',
            'system_user_password' => 'secret-pass-1', 'system_user_password_confirmation' => 'secret-pass-1', 'system_user_confirmed' => '1',
        ])->assertRedirect();

        $this->assertSame(User::where('username', 'dina.cruz')->value('id'), Subscription::withoutGlobalScopes()->latest('id')->value('user_id'));
    }
}
