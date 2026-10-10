<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSettingsOwnAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User} The school's signed-in admin and another account of the same school. */
    private function pair(): array
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'S1', 'name' => 'School One', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $make = fn (string $name) => User::factory()->create(['name' => $name, 'role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id, 'status' => 'active']);

        return [$make('Me'), $make('Other')];
    }

    public function test_a_school_user_cannot_change_another_account_and_sees_it_as_view_only(): void
    {
        [$me, $other] = $this->pair();
        $payload = ['school_id' => $me->school_id, 'email' => 'changed@example.com', 'status' => 'inactive'];

        $this->actingAs($me)->put(route('school-settings.users.update', $other->id), $payload)->assertForbidden();
        $this->actingAs($me)->put(route('school-settings.users.password', $other->id), ['school_id' => $me->school_id, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertForbidden();
        $this->assertSame([$other->email, 'active'], [$other->fresh()->email, $other->fresh()->status]);

        $html = $this->actingAs($me)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $me->school_id, 'tab' => 'users']))->assertOk()->getContent();
        $this->assertStringContainsString('View only', $html);
        $this->assertStringContainsString('data-user-edit="'.$me->id.'"', $html);
        $this->assertStringNotContainsString('data-user-edit="'.$other->id.'"', $html);
    }

    public function test_a_school_user_can_still_change_their_own_account_and_the_master_any_account(): void
    {
        [$me, $other] = $this->pair();

        $this->actingAs($me)->put(route('school-settings.users.update', $me->id), ['school_id' => $me->school_id, 'email' => 'mine@example.com', 'status' => 'active'])->assertRedirect();
        $this->assertSame('mine@example.com', $me->fresh()->email);

        $master = User::factory()->create(['role' => 'master_user']);
        $this->actingAs($master)->put(route('school-settings.users.update', $other->id), ['school_id' => $other->school_id, 'email' => 'by-master@example.com', 'role' => 'encoder', 'status' => 'active'])->assertRedirect();
        $this->assertSame('by-master@example.com', $other->fresh()->email);
    }
}
