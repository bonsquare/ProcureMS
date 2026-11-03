<?php

namespace Tests\Feature;

use App\Models\AppCseItem;
use App\Models\ChartOfAccount;
use App\Models\Organization;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipProject;
use App\Models\User;
use App\Services\AppCseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The last write and preview routes that had no test. */
class RemainingRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function viewerOf(array $fx): User
    {
        return User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);
    }

    private function sip(array $fx): SipProject
    {
        $this->actingAs($fx['user'])->post(route('planning.sip.store'), ['school_id' => $fx['school']->id, 'school_year' => 2026, 'pillar' => 'Access', 'kra' => 'KRA 1', 'project' => 'Original Project'])->assertSessionHasNoErrors();

        return SipProject::firstOrFail();
    }

    public function test_an_account_is_added_to_the_own_organization_and_needs_a_unique_code(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('chart-of-accounts.store'), ['code' => '5-99-01', 'title' => 'Training Expenses', 'category' => 'Expense'])->assertRedirect(route('chart-of-accounts'));
        $this->assertSame($fx['organization']->id, ChartOfAccount::withoutGlobalScopes()->where('code', '5-99-01')->value('organization_id'));

        $this->post(route('chart-of-accounts.store'), ['code' => '5-99-01', 'title' => 'Again', 'category' => 'Expense'])->assertSessionHasErrors('code');
        $this->post(route('chart-of-accounts.store'), ['code' => '', 'title' => '', 'category' => ''])->assertSessionHasErrors(['code', 'title', 'category']);
        $this->actingAs($this->viewerOf($fx))->post(route('chart-of-accounts.store'), ['code' => '5-99-02', 'title' => 'x', 'category' => 'Expense'])->assertForbidden();
        $this->assertSame(1, ChartOfAccount::withoutGlobalScopes()->where('organization_id', $other['organization']->id)->count());
    }

    public function test_only_a_master_may_add_a_school_with_its_first_user(): void
    {
        $fx = SobFixture::make();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $form = ['name' => 'New Hope Elementary', 'status' => 'active', 'system_user_name' => 'Head New', 'system_user_email' => 'head.new@example.com', 'system_user_role' => 'school_admin', 'system_user_password' => 'a-long-password-1', 'system_user_password_confirmation' => 'a-long-password-1'];

        $this->actingAs($fx['user'])->post(route('schools.store'), $form)->assertForbidden();
        $this->assertSame(0, School::withoutGlobalScopes()->where('name', 'New Hope Elementary')->count());

        $this->actingAs($master)->post(route('schools.store'), $form)->assertSessionHasNoErrors();
        $school = School::withoutGlobalScopes()->where('name', 'New Hope Elementary')->firstOrFail();
        $user = User::where('email', 'head.new@example.com')->firstOrFail();
        $this->assertSame($school->id, $user->school_id);
        $this->assertSame($school->organization_id, $user->organization_id);
        $this->assertNotNull(Organization::find($school->organization_id));

        $this->post(route('schools.store'), ['name' => 'Another'] + $form)->assertSessionHasErrors('system_user_email');
        $this->post(route('schools.store'), ['system_user_email' => 'x@example.com', 'system_user_password' => 'short', 'system_user_password_confirmation' => 'short'] + $form)->assertSessionHasErrors('system_user_password');
    }

    public function test_the_sip_previews_show_the_typed_entry_and_save_nothing(): void
    {
        $fx = SobFixture::make();
        $sip = $this->sip($fx);
        $this->post(route('planning.sip.activities.store', $sip), ['activity' => 'Original Activity', 'financial_year1' => 100])->assertSessionHasNoErrors();
        $activity = SipActivity::firstOrFail();

        $html = $this->post(route('planning.sip.update.preview', $sip), ['school_id' => $fx['school']->id, 'school_year' => 2026, 'pillar' => 'Access', 'kra' => 'KRA 1', 'project' => 'Typed Project Sigma'])->assertOk()->getContent();
        $this->assertStringContainsString('Typed Project Sigma', $html);
        $this->assertSame('Original Project', $sip->fresh()->project);

        $html = $this->post(route('planning.sip.activities.preview', $sip), ['activity' => 'Typed Activity Theta', 'financial_year1' => 5])->assertOk()->getContent();
        $this->assertStringContainsString('Typed Activity Theta', $html);
        $this->assertSame(1, SipActivity::count());

        $html = $this->post(route('planning.sip.activities.update.preview', $activity), ['activity' => 'Changed Activity Kappa', 'financial_year1' => 7])->assertOk()->getContent();
        $this->assertStringContainsString('Changed Activity Kappa', $html);
        $this->assertSame('Original Activity', $activity->fresh()->activity);
    }

    public function test_the_sip_previews_are_refused_for_a_viewer_and_hidden_from_another_school(): void
    {
        $other = SobFixture::make();
        $fx = SobFixture::make();
        $sip = $this->sip($fx);

        $this->actingAs($this->viewerOf($fx))->post(route('planning.sip.update.preview', $sip), ['project' => 'x'])->assertForbidden();
        $this->post(route('planning.sip.activities.preview', $sip), ['activity' => 'x'])->assertForbidden();
        $this->actingAs($other['user'])->post(route('planning.sip.activities.preview', $sip), ['activity' => 'x'])->assertNotFound();
        $this->assertSame('Original Project', $sip->fresh()->project);
    }

    public function test_the_app_cse_previews_show_the_typed_entry_and_save_nothing(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);
        $item = app(AppCseService::class)->addCustomItem($plan, ['category' => 'Other', 'description' => 'Original Custom', 'unit' => 'pc', 'unit_price' => 5]);

        $this->post(route('planning.app-cse.custom.preview', $plan), ['category' => 'Other', 'description' => 'Typed Custom Zeta', 'unit' => 'box', 'unit_price' => 9])->assertOk();
        $this->assertSame(0, AppCseItem::where('description', 'Typed Custom Zeta')->count());
        $this->assertSame(1, AppCseItem::count());

        $this->post(route('planning.app-cse.items.preview', $plan), ['cse_catalog_item_id' => 999999])->assertOk();
        $this->assertSame(1, AppCseItem::count());

        $this->post(route('planning.app-cse.items.update.preview', $item), ['category' => 'Other', 'description' => 'Changed Custom Omega', 'unit' => 'pc', 'unit_price' => 5])->assertOk();
        $this->assertSame('Original Custom', $item->fresh()->description);
    }

    public function test_the_app_cse_previews_are_refused_for_a_viewer(): void
    {
        $fx = SobFixture::make();
        $plan = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);
        $item = app(AppCseService::class)->addCustomItem($plan, ['category' => 'Other', 'description' => 'Original Custom', 'unit' => 'pc', 'unit_price' => 5]);
        $this->actingAs($this->viewerOf($fx));

        $this->post(route('planning.app-cse.items.preview', $plan), [])->assertForbidden();
        $this->post(route('planning.app-cse.custom.preview', $plan), [])->assertForbidden();
        $this->post(route('planning.app-cse.items.update.preview', $item), [])->assertForbidden();
    }
}
