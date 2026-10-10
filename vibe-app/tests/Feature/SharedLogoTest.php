<?php

namespace Tests\Feature;

use App\Models\AgencySetting;
use App\Models\Organization;
use App\Models\School;
use App\Models\SharedLogo;
use App\Models\Subscription;
use App\Models\User;
use App\Support\OfficialDocument;
use App\Support\SharedLogoBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedLogoTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code, ?string $division = 'Cebu City', string $region = 'Region VII'): array
    {
        $organization = Organization::create(['name' => "Org {$code}", 'slug' => "org-{$code}", 'status' => 'active']);
        $school = School::create([
            'organization_id' => $organization->id, 'code' => $code, 'name' => "School {$code}",
            'region' => $region, 'division' => $division, 'status' => 'active',
        ]);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    private function storedLogo(): string
    {
        return UploadedFile::fake()->create('logo.png', 4, 'image/png')->store('logos', 'public');
    }

    public function test_division_key_ignores_case_and_spaces(): void
    {
        $this->assertSame(
            SharedLogo::divisionKey('Region VII', ' Cebu  City '),
            SharedLogo::divisionKey('region vii', 'cebu city'),
        );
    }

    public function test_blank_division_has_no_key(): void
    {
        $this->assertNull(SharedLogo::divisionKey('Region VII', ''));
        $this->assertNull(SharedLogo::divisionKey('Region VII', '   '));
        $this->assertNull(SharedLogo::divisionKey(null, null));
    }

    public function test_a_school_uses_the_shared_logo_of_its_division(): void
    {
        Storage::fake('public');
        [, $schoolA] = $this->school('A');
        [, $schoolB] = $this->school('B', ' cebu city ', 'region vii');
        $path = $this->storedLogo();
        SharedLogo::create(['kind' => 'division', 'key' => SharedLogo::divisionKey('Region VII', 'Cebu City'), 'path' => $path]);

        [, $right] = OfficialDocument::logos($schoolB, null);

        $this->assertStringEndsWith('storage/'.$path, $right);
        $this->assertNotNull(SharedLogo::forDivision($schoolA));
    }

    public function test_a_school_with_a_blank_division_never_gets_a_shared_logo(): void
    {
        Storage::fake('public');
        [, $school] = $this->school('C', '');
        SharedLogo::create(['kind' => 'division', 'key' => '|', 'path' => $this->storedLogo()]);

        $this->assertNull(SharedLogo::forDivision($school));
        $this->assertNull(OfficialDocument::logos($school, null)[1]);
    }

    public function test_a_school_logo_wins_over_the_division_logo(): void
    {
        Storage::fake('public');
        [, $school] = $this->school('D');
        $own = $this->storedLogo();
        $school->update(['logo_path' => $own]);
        SharedLogo::create(['kind' => 'division', 'key' => SharedLogo::divisionKey('Region VII', 'Cebu City'), 'path' => $this->storedLogo()]);

        [, $right] = OfficialDocument::logos($school->fresh(), null);

        $this->assertStringEndsWith('storage/'.$own, $right);
    }

    public function test_the_shared_department_logo_is_used_for_every_school(): void
    {
        Storage::fake('public');
        [, $schoolA] = $this->school('E');
        [, $schoolB] = $this->school('F', 'Other Division');
        $path = $this->storedLogo();
        SharedLogo::create(['kind' => 'department', 'key' => 'deped', 'path' => $path]);

        foreach ([$schoolA, $schoolB] as $school) {
            [$left] = OfficialDocument::logos($school, null);
            $this->assertStringEndsWith('storage/'.$path, $left);
        }
    }

    public function test_existing_division_logo_is_backfilled_once(): void
    {
        Storage::fake('public');
        [$organization, $school] = $this->school('G');
        AgencySetting::withoutGlobalScopes()->create([
            'organization_id' => $organization->id, 'division_logo_path' => 'logos/old-division.png',
            'department_logo_path' => 'logos/old-dept.png',
        ]);

        SharedLogoBackfill::run();
        SharedLogoBackfill::run();

        $this->assertSame(1, SharedLogo::where('kind', 'division')->count());
        $this->assertSame('logos/old-division.png', SharedLogo::forDivision($school)->path);
        $this->assertSame('logos/old-dept.png', SharedLogo::department()->path);
    }

    private function uploadLogo(User $user, School $school, string $field)
    {
        return $this->actingAs($user)->post(route('school-settings.agency'), [
            'school_id' => $school->id,
            'department_name' => 'Department of Education',
            'region_name' => 'Region VII',
            'division_office' => 'Cebu City',
            $field => UploadedFile::fake()->create('new.png', 4, 'image/png'),
        ]);
    }

    public function test_first_upload_of_a_division_logo_is_shared_with_other_schools(): void
    {
        Storage::fake('public');
        [, $schoolA, $adminA] = $this->school('H');
        [, $schoolB] = $this->school('I');

        $this->uploadLogo($adminA, $schoolA, 'division_logo')->assertSessionHasNoErrors();

        $row = SharedLogo::forDivision($schoolB);
        $this->assertNotNull($row);
        Storage::disk('public')->assertExists($row->path);
        $this->assertSame($adminA->id, $row->uploaded_by);
    }

    public function test_a_school_admin_cannot_replace_an_existing_shared_logo(): void
    {
        Storage::fake('public');
        [, $schoolA, $adminA] = $this->school('J');
        $path = $this->storedLogo();
        SharedLogo::create(['kind' => 'division', 'key' => SharedLogo::divisionKey('Region VII', 'Cebu City'), 'path' => $path]);

        $this->uploadLogo($adminA, $schoolA, 'division_logo')->assertForbidden();

        $this->assertSame($path, SharedLogo::forDivision($schoolA)->path);
    }

    public function test_the_master_user_can_replace_a_shared_logo(): void
    {
        Storage::fake('public');
        [, $schoolA] = $this->school('K');
        $master = User::factory()->create(['role' => 'master_user']);
        $old = $this->storedLogo();
        SharedLogo::create(['kind' => 'division', 'key' => SharedLogo::divisionKey('Region VII', 'Cebu City'), 'path' => $old]);

        $this->uploadLogo($master, $schoolA, 'division_logo')->assertSessionHasNoErrors();

        $this->assertNotSame($old, SharedLogo::forDivision($schoolA)->path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_department_logo_is_one_for_all(): void
    {
        Storage::fake('public');
        [, $schoolA, $adminA] = $this->school('L');
        [, $schoolB, $adminB] = $this->school('M', 'Other Division');

        $this->uploadLogo($adminA, $schoolA, 'department_logo')->assertSessionHasNoErrors();
        $first = SharedLogo::department()->path;
        $this->uploadLogo($adminB, $schoolB, 'department_logo')->assertForbidden();

        $this->assertSame($first, SharedLogo::department()->path);
        $this->assertSame(1, SharedLogo::where('kind', 'department')->count());
    }

    public function test_a_division_logo_needs_a_division_to_be_named(): void
    {
        Storage::fake('public');
        [, $school, $admin] = $this->school('N', '');

        $this->actingAs($admin)->post(route('school-settings.agency'), [
            'school_id' => $school->id, 'department_name' => 'Department of Education',
            'division_logo' => UploadedFile::fake()->create('new.png', 4, 'image/png'),
        ])->assertSessionHasErrors('division_logo');

        $this->assertSame(0, SharedLogo::where('kind', 'division')->count());
    }

    public function test_the_settings_page_shows_a_shared_logo_read_only_for_a_school_admin(): void
    {
        Storage::fake('public');
        [, $school, $admin] = $this->school('P');
        $master = User::factory()->create(['role' => 'master_user']);
        SharedLogo::create(['kind' => 'division', 'key' => SharedLogo::divisionKey('Region VII', 'Cebu City'), 'path' => $this->storedLogo()]);

        $this->actingAs($admin)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))
            ->assertOk()->assertSee('Shared logo. Only the master user can change it.');
        $this->actingAs($master)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))
            ->assertOk()->assertDontSee('Shared logo. Only the master user can change it.');
    }
}
