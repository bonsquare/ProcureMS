<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Edits and deletes of records (RequireActionPin) need a reason and the user's own PIN, and the reason is kept in the audit log. */
class ActionPinGuardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withoutActionPin = true;

    private function unitAndUser(): array
    {
        $organization = Organization::create(['name' => 'guard', 'slug' => 'guard', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'GRD', 'name' => 'Guard School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $this->actingAs($user)->post(route('units.store'), ['name' => 'carboy']);

        return [Unit::where('name', 'carboy')->firstOrFail(), $user];
    }

    public function test_a_delete_without_the_reason_and_pin_is_refused(): void
    {
        [$unit] = $this->unitAndUser();

        $this->delete(route('units.destroy', $unit))->assertSessionHasErrors(['reason', 'pin']);
        $this->assertDatabaseHas('units', ['name' => 'carboy']);
    }

    public function test_a_wrong_pin_is_refused_and_nothing_is_deleted(): void
    {
        [$unit] = $this->unitAndUser();

        $this->delete(route('units.destroy', $unit), ['reason' => 'Not needed', 'pin' => '000000'])->assertSessionHasErrors('pin');
        $this->assertDatabaseHas('units', ['name' => 'carboy']);
    }

    public function test_the_right_pin_deletes_and_the_reason_is_recorded(): void
    {
        [$unit] = $this->unitAndUser();

        $this->delete(route('units.destroy', $unit), ['reason' => 'Duplicate unit', 'pin' => '123456'])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('units', ['name' => 'carboy']);
        $this->assertSame('Duplicate unit', AuditLog::where('action', 'units_destroy')->latest('id')->first()->metadata['reason']);
    }
}
