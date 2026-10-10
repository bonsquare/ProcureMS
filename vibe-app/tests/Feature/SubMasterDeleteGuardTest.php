<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubMasterDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private const DELETE_ROUTES = [
        'chart-of-accounts.destroy', 'aip.destroy', 'aip.kras.destroy', 'budget.allocation.destroy', 'planning.app.items.destroy',
        'planning.ppmp.destroy', 'planning.sip.activities.destroy', 'planning.sip.destroy', 'units.destroy', 'school-settings.employees.destroy',
    ];

    private function url(string $name): string
    {
        $parameters = Route::getRoutes()->getByName($name)->parameterNames();

        return route($name, array_fill_keys($parameters, 1));
    }

    private function refusals(User $user): int
    {
        return AuditLog::where(['user_id' => $user->id, 'action' => 'sub_master_action_refused'])->count();
    }

    private function subMaster(array $extra = []): User
    {
        return User::factory()->subMaster([...SubMasterAccess::defaults(), ...$extra])->create();
    }

    public function test_each_delete_route_is_refused_without_the_delete_switch_and_audited(): void
    {
        $sub = $this->subMaster();

        foreach (self::DELETE_ROUTES as $name) {
            $this->actingAs($sub)->delete($this->url($name))->assertForbidden();
        }

        $this->assertSame(count(self::DELETE_ROUTES), $this->refusals($sub));
    }

    public function test_each_delete_route_is_not_refused_by_the_guard_with_the_delete_switch(): void
    {
        $sub = $this->subMaster(['delete']);

        foreach (self::DELETE_ROUTES as $name) {
            $this->actingAs($sub)->delete($this->url($name));
        }

        $this->assertSame(0, $this->refusals($sub));
    }

    public function test_user_deactivation_and_budget_close_follow_their_own_switches(): void
    {
        $sub = $this->subMaster();
        $this->actingAs($sub)->post($this->url('school-management.users.deactivate'), [])->assertForbidden();
        $this->actingAs($sub)->post(route('budget.allocation.close'), [])->assertForbidden();
        $this->assertSame(2, $this->refusals($sub));

        $deactivator = $this->subMaster(['deactivate_user']);
        $this->actingAs($deactivator)->post($this->url('school-management.users.deactivate'), []);
        $this->actingAs($deactivator)->post(route('budget.allocation.close'), [])->assertForbidden();
        $this->assertSame(1, $this->refusals($deactivator));

        $closer = $this->subMaster(['close_budget']);
        $this->actingAs($closer)->post(route('budget.allocation.close'), []);
        $this->actingAs($closer)->post($this->url('school-management.users.deactivate'), [])->assertForbidden();
        $this->assertSame(1, $this->refusals($closer));
    }

    public function test_delete_does_not_open_deactivate_or_close(): void
    {
        $sub = $this->subMaster(['delete']);

        $this->actingAs($sub)->post($this->url('school-management.users.deactivate'), [])->assertForbidden();
        $this->actingAs($sub)->post(route('budget.allocation.close'), [])->assertForbidden();
        $this->assertSame(2, $this->refusals($sub));
    }

    public function test_the_personal_drive_routes_are_never_blocked(): void
    {
        $sub = $this->subMaster();

        $this->actingAs($sub)->delete(route('google-drive.disconnect'))->assertRedirect(route('google-drive'));
        $this->actingAs($sub)->delete($this->url('drive-files.destroy'))->assertNotFound();
        $this->assertSame(0, $this->refusals($sub));
    }

    public function test_method_spoofing_is_blocked_too(): void
    {
        $sub = $this->subMaster();

        $this->actingAs($sub)->post($this->url('aip.destroy'), ['_method' => 'DELETE'])->assertForbidden();

        $this->assertSame(1, $this->refusals($sub));
    }

    public function test_turning_a_switch_off_applies_on_the_next_request(): void
    {
        $sub = $this->subMaster(['delete']);
        $this->actingAs($sub)->delete($this->url('aip.destroy'));
        $this->assertSame(0, $this->refusals($sub));

        $sub->forceFill(['access' => SubMasterAccess::defaults()])->save();

        $this->actingAs($sub->fresh())->delete($this->url('aip.destroy'))->assertForbidden();
        $this->assertSame(1, $this->refusals($sub));
    }

    public function test_the_master_and_a_school_admin_are_not_affected(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);
        $admin = User::factory()->create(['role' => 'school_admin']);

        foreach ([$master, $admin] as $user) {
            $this->actingAs($user)->delete($this->url('aip.destroy'));
            $this->actingAs($user)->post(route('budget.allocation.close'), []);
            $this->assertSame(0, $this->refusals($user));
        }
    }

    public function test_the_refusal_says_why(): void
    {
        $this->actingAs($this->subMaster())->delete($this->url('aip.destroy'))
            ->assertForbidden()->assertSee("Your account's access does not allow this action.");
    }
}
