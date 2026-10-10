<?php

namespace Tests\Feature;

use App\Models\AgencySetting;
use App\Models\Organization;
use App\Models\School;
use App\Models\SharedLogo;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PlaceNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceNamesTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code, ?string $region = null, ?string $division = null, ?string $district = null): School
    {
        $organization = Organization::create(['name' => "Org {$code}", 'slug' => "org-{$code}", 'status' => 'active']);

        return School::create([
            'organization_id' => $organization->id, 'code' => $code, 'name' => "School {$code}", 'status' => 'active',
            'region' => $region, 'division' => $division, 'district' => $district,
        ]);
    }

    private function admin(School $school): User
    {
        Subscription::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $school->organization_id, 'school_id' => $school->id]);
    }

    public function test_region_numbers_become_roman_numerals(): void
    {
        foreach (['Region 7', 'region 07', ' region   vii ', 'REGION VII', '7', 'VII'] as $typed) {
            $this->assertSame('Region VII', PlaceNames::canonicalRegion($typed), $typed);
        }
        $this->assertSame('Region IV-A', PlaceNames::canonicalRegion('4a'));
        $this->assertSame('Region IV-B', PlaceNames::canonicalRegion('Region 4-B'));
        $this->assertSame('Region XII', PlaceNames::canonicalRegion('region 12'));
        $this->assertSame('NCR', PlaceNames::canonicalRegion('NCR'));
        $this->assertSame('National Capital Region', PlaceNames::canonicalRegion('National  Capital   Region'));
        $this->assertNull(PlaceNames::canonicalRegion('   '));
        $this->assertNull(PlaceNames::canonicalRegion(null));
    }

    public function test_suggestions_list_each_existing_name_once_with_its_most_used_spelling(): void
    {
        $this->school('A', 'Region VII', 'Cebu City', 'District 1');
        $this->school('B', 'Region VII', 'Cebu City');
        $this->school('C', 'region vii', 'cebu city');
        AgencySetting::withoutGlobalScopes()->create(['organization_id' => School::where('code', 'A')->value('organization_id'), 'region_name' => 'Region VII', 'division_office' => 'Cebu City']);

        $this->assertSame(['Region VII'], PlaceNames::suggestions('region')->all());
        $this->assertSame(['Cebu City'], PlaceNames::suggestions('division')->all());
        $this->assertSame(['District 1'], PlaceNames::suggestions('district')->all());
    }

    public function test_a_typed_name_snaps_to_the_spelling_already_in_the_system(): void
    {
        $this->school('A', 'Region VII', 'Cebu City');

        $this->assertSame('Cebu City', PlaceNames::snap('division', 'cebu   city'));
        $this->assertSame('Region VII', PlaceNames::snap('region', 'Region 7'));
        $this->assertSame('Mandaue City', PlaceNames::snap('division', ' Mandaue   City '));
        $this->assertNull(PlaceNames::snap('division', '  '));
    }

    public function test_the_registration_page_suggests_the_names_already_in_the_system(): void
    {
        $this->school('A', 'Region VII', 'Cebu City', 'District 1');

        $this->get(route('register'))->assertOk()
            ->assertSee('<datalist id="place-regions">', false)->assertSee('<option value="Region VII">', false)
            ->assertSee('<option value="Cebu City">', false)->assertSee('<option value="District 1">', false)
            ->assertSee('list="place-regions"', false)->assertSee('list="place-divisions"', false);
    }

    public function test_the_suggestions_never_include_school_or_user_names(): void
    {
        $this->school('SECRET1', 'Region VII', 'Cebu City');

        $html = $this->get(route('register'))->assertOk()->getContent();
        preg_match_all('/<datalist.*?<\/datalist>/s', $html, $lists);

        $this->assertCount(3, $lists[0]);
        $this->assertStringNotContainsString('SECRET1', implode('', $lists[0]));
    }

    public function test_school_details_are_saved_with_the_canonical_names(): void
    {
        $this->school('A', 'Region VII', 'Cebu City');
        $school = $this->school('B');
        $admin = $this->admin($school);

        $this->actingAs($admin)->post(route('school-settings.school'), [
            'school_id' => $school->id, 'name' => 'School B', 'region' => 'region 7', 'division' => 'cebu  city',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Region VII', 'Cebu City'], [$school->fresh()->region, $school->fresh()->division]);
    }

    public function test_agency_region_and_division_are_saved_with_the_canonical_names(): void
    {
        $this->school('A', 'Region VII', 'Cebu City');
        $school = $this->school('B');
        $admin = $this->admin($school);

        $this->actingAs($admin)->post(route('school-settings.agency'), [
            'school_id' => $school->id, 'department_name' => 'Department of Education',
            'region_name' => '7', 'division_office' => 'CEBU CITY',
        ])->assertSessionHasNoErrors();

        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->firstOrFail();
        $this->assertSame(['Region VII', 'Cebu City'], [$agency->region_name, $agency->division_office]);
    }

    public function test_the_shared_logo_key_treats_region_7_and_region_vii_alike(): void
    {
        $this->assertSame(SharedLogo::divisionKey('Region 7', 'Cebu City'), SharedLogo::divisionKey('Region VII', 'cebu city'));
    }
}
