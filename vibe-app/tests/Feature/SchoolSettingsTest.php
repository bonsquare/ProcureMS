<?php

namespace Tests\Feature;

use App\Models\AgencySetting;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StaffRoleOption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
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

    private function png(string $name): UploadedFile
    {
        // A real 1x1 PNG, so the image rule passes without needing the GD extension.
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    public function test_the_page_shows_three_tabs_and_the_generated_ids(): void
    {
        [, $school, $user] = $this->tenant('settings-a');

        $this->actingAs($user)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))->assertOk()
            ->assertSee('School Information')->assertSee('System Users')->assertSee('Employees &amp; Roles', false)
            ->assertSee('Public Header')->assertSee('School Division Office')->assertSee('Department / Agency address')->assertSee('Division Office Details')->assertSee('District Office')->assertSee('School details')->assertDontSee('District logo')->assertDontSee('Fiscal year &amp; document numbering', false)->assertSee('data-edit', false)->assertSee('data-cancel', false)->assertDontSee('District details')
            ->assertDontSee('Division head')->assertDontSee('District Supervisor')->assertDontSee('School head')
            ->assertSee('(auto-generated)')->assertSee($school->code)->assertDontSee('User ID')
            ->assertSee('Agency / Department logo')->assertSee('Division office logo')->assertSee('School logo');

        // The User ID lives with the system users, where it is shown per account.
        $this->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'users']))->assertOk()->assertSee($user->fresh()->user_code);
        $this->assertMatchesRegularExpression('/^USR-\d{6}$/', $user->fresh()->user_code);
        $this->assertNotEmpty($user->fresh()->username);
    }

    public function test_agency_district_and_school_details_and_logos_are_saved(): void
    {
        Storage::fake('public');
        [, $school, $user] = $this->tenant('settings-b');

        $this->actingAs($user)->post(route('school-settings.agency'), [
            'department_name' => 'Department of Education', 'division_office' => 'Schools Division Office of Cotabato', 'region_name' => 'Region XII', 'district_name' => 'District III',
            'district_email' => 'district@example.com', 'district_phone' => '0917 000 1111', 'district_address' => 'Magpet East',
            'department_logo' => $this->png('agency.png'), 'division_logo' => $this->png('division.png'),
        ])->assertRedirect();
        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->firstOrFail();
        $this->assertSame('Schools Division Office of Cotabato', $agency->division_office);
        $this->assertSame('district@example.com', $agency->district_email);
        $this->assertSame('0917 000 1111', $agency->district_phone);
        Storage::disk('public')->assertExists($agency->department_logo_path);
        Storage::disk('public')->assertExists($agency->division_logo_path);

        // Each section saves on its own and leaves the others untouched.
        $this->post(route('school-settings.agency'), ['division_email' => 'sdo@example.com', 'division_phone' => '064 111 2222', 'region_name' => 'Region XII', 'division_address' => 'Kidapawan City'])->assertRedirect();
        $this->post(route('school-settings.agency'), ['district_name' => 'District IV'])->assertRedirect();
        $agency->refresh();
        $this->assertSame('sdo@example.com', $agency->division_email);
        $this->assertSame('District IV', $agency->district_name);
        $this->assertSame('Department of Education', $agency->department_name);
        $this->assertSame('district@example.com', $agency->district_email);

        // The School ID is generated: a submitted code never changes it.
        $this->post(route('school-settings.school'), ['school_id' => $school->id, 'name' => 'Renamed School', 'code' => 'HACKED', 'school_logo' => $this->png('school.png')])->assertRedirect();
        $fresh = $school->fresh();
        $this->assertSame('Renamed School', $fresh->name);
        $this->assertSame('SETTINGS-B', $fresh->code);
        Storage::disk('public')->assertExists($fresh->logo_path);
    }

    public function test_an_employee_can_hold_many_roles_and_documents_still_find_them(): void
    {
        [, $school, $user] = $this->tenant('settings-c');

        $this->actingAs($user)->post(route('school-settings.employees.store'), [
            'school_id' => $school->id, 'name' => 'Rizza Mae Orong', 'position' => 'Administrative Officer II',
            'roles' => ['bac_role' => ['BAC Secretariat', 'BAC Member'], 'procurement_role' => ['Requesting Officer', 'Procurement Officer'], 'document_role' => ['Disbursing Officer', 'Property Custodian']],
        ])->assertRedirect();

        $staff = SchoolStaff::where('name', 'Rizza Mae Orong')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EMP-\d{6}$/', $staff->employee_no);
        $this->assertTrue($staff->hasRole('bac_role', 'BAC Member'));
        $this->assertTrue($staff->hasRole('procurement_role', 'procurement officer'));
        $this->assertSame(['Disbursing Officer', 'Property Custodian'], $staff->rolesFor('document_role'));

        // Edit: take one role away, add another.
        $this->put(route('school-settings.employees.update', $staff), ['school_id' => $school->id, 'name' => 'Rizza Mae Orong', 'position' => 'Administrative Officer II', 'roles' => ['bac_role' => ['BAC Member'], 'procurement_role' => ['Approver']]])->assertRedirect();
        $staff->refresh();
        $this->assertFalse($staff->hasRole('bac_role', 'BAC Secretariat'));
        $this->assertTrue($staff->hasRole('procurement_role', 'Approver'));
        $this->assertNull($staff->document_role);

        $this->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'staff']))->assertOk()
            ->assertSee('Rizza Mae Orong')->assertSee('Administrative Officer II')->assertSee($staff->employee_no)->assertSee('BAC Member')->assertSee('Approver');
    }

    public function test_a_new_role_can_be_added_and_used(): void
    {
        [$organization, $school, $user] = $this->tenant('settings-d');

        $this->actingAs($user)->post(route('school-settings.roles.store'), ['school_id' => $school->id, 'role_group' => 'document_role', 'name' => 'Records Custodian'])->assertRedirect();
        $this->assertDatabaseHas('staff_role_options', ['organization_id' => $organization->id, 'role_group' => 'document_role', 'name' => 'Records Custodian']);

        // A built-in role cannot be added twice.
        $this->post(route('school-settings.roles.store'), ['school_id' => $school->id, 'role_group' => 'bac_role', 'name' => 'bac member']);
        $this->assertSame(1, StaffRoleOption::count());

        $this->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'staff']))->assertOk()->assertSee('Records Custodian');
    }

    public function test_system_users_can_be_edited_and_given_a_new_password(): void
    {
        [$organization, $school, $admin] = $this->tenant('settings-e');

        // Users cannot be added from School Settings: one user manages one school.
        $this->assertFalse(Route::has('school-settings.users.store'));
        $ana = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'cashier', 'username' => 'ana.reyes', 'email' => 'ana@example.com', 'name' => 'Ana Reyes']);
        $this->assertMatchesRegularExpression('/^USR-\d{6}$/', $ana->user_code);
        $this->actingAs($admin);

        $this->put(route('school-settings.users.update', $ana), ['school_id' => $school->id, 'name' => 'Ana R. Reyes', 'username' => 'ana.reyes', 'email' => 'ana@example.com', 'position' => 'Senior Cashier', 'role' => 'cashier', 'status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $ana->fresh()->status);

        $this->put(route('school-settings.users.password', $ana), ['school_id' => $school->id, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect();
        $this->assertNotNull($ana->fresh()->password_changed_at);
        $this->assertTrue(Hash::check('brand-new-pass', $ana->fresh()->password));

        // Own password needs the current one.
        $this->put(route('school-settings.users.password', $admin), ['school_id' => $school->id, 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'])->assertSessionHasErrors('current_password');
    }

    public function test_sign_in_accepts_a_username_and_blocks_inactive_accounts(): void
    {
        [, $school] = $this->tenant('settings-f');
        $user = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'viewer', 'username' => 'maria.santos', 'password' => 'a-good-password']);

        $this->post(route('login.store'), ['email' => 'maria.santos', 'password' => 'a-good-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post(route('logout'));
        $user->update(['status' => 'inactive']);
        $this->post(route('login.store'), ['email' => 'maria.santos', 'password' => 'a-good-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_settings_are_isolated_between_schools(): void
    {
        [, $schoolA, $adminA] = $this->tenant('settings-g');
        [, $schoolB, $adminB] = $this->tenant('settings-h');
        $staffB = SchoolStaff::create(['organization_id' => $schoolB->organization_id, 'school_id' => $schoolB->id, 'name' => 'Other Person']);

        $this->actingAs($adminA)->put(route('school-settings.employees.update', $staffB), ['school_id' => $schoolB->id, 'name' => 'Hijacked'])->assertNotFound();
        $this->put(route('school-settings.users.update', $adminB), ['school_id' => $schoolB->id, 'name' => 'Hijacked', 'username' => 'x', 'email' => 'x@example.com', 'role' => 'viewer', 'status' => 'active'])->assertNotFound();
        $this->assertSame('Other Person', $staffB->fresh()->name);
    }

    public function test_a_master_sees_the_logos_of_the_school_they_are_editing(): void
    {
        Storage::fake('public');
        [$organizationA] = $this->tenant('settings-i');
        [$organizationB, $schoolB] = $this->tenant('settings-j');
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        // Another organization's agency record is older, so a bare first() would pick it.
        AgencySetting::withoutGlobalScopes()->create(['organization_id' => $organizationA->id, 'department_name' => 'Other agency']);
        Storage::disk('public')->put('logos/agency-b.png', 'png');
        Storage::disk('public')->put('logos/division-b.png', 'png');
        AgencySetting::withoutGlobalScopes()->create(['organization_id' => $organizationB->id, 'department_name' => 'Agency B', 'department_logo_path' => 'logos/agency-b.png', 'division_logo_path' => 'logos/division-b.png']);

        $this->actingAs($master)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $schoolB->id]))->assertOk()
            ->assertSee('storage/logos/agency-b.png', false)->assertSee('storage/logos/division-b.png', false)
            ->assertSee('Agency B')->assertDontSee('Other agency');
    }

    public function test_the_logo_boxes_say_what_can_be_uploaded(): void
    {
        [, $school, $user] = $this->tenant('settings-k');

        $this->actingAs($user)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))->assertOk()
            ->assertSee('Logo requirements')->assertSee('PNG, JPG, WebP or GIF')->assertSee('Up to 2 MB')->assertSee('Square, 300 × 300 px or more')->assertSee('data-logo-info', false);
        $this->assertSame(3, substr_count($this->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))->getContent(), 'Logo requirements'), 'every logo shows the same requirements');

        // Too large (3 MB) and wrong format both come back with a plain message.
        $this->post(route('school-settings.agency'), ['school_id' => $school->id, 'department_logo' => UploadedFile::fake()->create('big.png', 3000, 'image/png')])
            ->assertSessionHasErrors(['department_logo' => 'The agency logo is too large. The limit is 2 MB.']);
        $this->post(route('school-settings.agency'), ['school_id' => $school->id, 'division_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors(['division_logo' => 'The division office logo must be a PNG, JPG, WebP or GIF image.']);
    }

    public function test_the_username_never_changes_and_only_the_master_user_changes_roles(): void
    {
        [, $school, $admin] = $this->tenant('settings-l');
        $worker = User::factory()->create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'role' => 'cashier', 'username' => 'fixed.name']);

        // A school administrator can edit the details but neither the username nor the role.
        $this->actingAs($admin)->put(route('school-settings.users.update', $worker), [
            'school_id' => $school->id, 'name' => 'Renamed Worker', 'username' => 'sneaky.change', 'email' => $worker->email, 'position' => 'Senior Cashier',
            'role' => 'approver', 'status' => 'active',
        ])->assertRedirect();
        $worker->refresh();
        $this->assertNotSame('Renamed Worker', $worker->name, 'the full name never changes');
        $this->assertSame('Senior Cashier', $worker->position);
        $this->assertSame('fixed.name', $worker->username);
        $this->assertSame('cashier', $worker->role);

        // The page says so, and shows each person's official station (the school).
        $this->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'users']))->assertOk()
            ->assertSee('cannot be changed')->assertSee('master user only')->assertSee('Official station')->assertSee($school->name)
            ->assertDontSee('Add user');

        // The master user does change the role, but still never the username.
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master)->put(route('school-settings.users.update', $worker), [
            'school_id' => $school->id, 'name' => 'Renamed Worker', 'username' => 'another.try', 'email' => $worker->email, 'role' => 'approver', 'status' => 'active',
        ])->assertRedirect();
        $worker->refresh();
        $this->assertSame('approver', $worker->role);
        $this->assertSame('fixed.name', $worker->username);
        $this->assertNotSame('Renamed Worker', $worker->name);
    }
}
