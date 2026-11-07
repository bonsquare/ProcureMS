<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saving the print settings asks for the reason and the user's PIN. */
class PrintSettingsPinTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withoutActionPin = true;

    public function test_it_asks_for_the_reason_and_pin(): void
    {
        $organization = Organization::create(['name' => 'pin', 'slug' => 'pin', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'PIN', 'name' => 'Pin School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        $form = ['school_id' => $school->id, 'default' => ['top' => '15', 'right' => '10', 'bottom' => '15', 'left' => '10']];

        $this->actingAs($user)->put(route('school-settings.printing.update'), $form)->assertSessionHasErrors(['reason', 'pin']);
        $this->put(route('school-settings.printing.update'), $form + ['reason' => 'Printer margins', 'pin' => '123456'])->assertSessionHasNoErrors();
    }
}
