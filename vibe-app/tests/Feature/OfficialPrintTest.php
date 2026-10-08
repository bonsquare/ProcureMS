<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\OfficialDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialPrintTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SCH', 'name' => 'Sample School']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    public function test_official_print_pages_share_the_paper_size_toolbar(): void
    {
        [$organization, $school, $user] = $this->tenant();
        $this->actingAs($user);
        $aip = Aip::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'fiscal_year' => 2026]);

        foreach ([route('aip.print', $aip), route('planning.sip.print', ['school_id' => $school->id, 'start_year' => 2026])] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Paper Size')->assertSee('Orientation')->assertSee('Long Bond')->assertSee('Custom')
                ->assertSee('Print / Save as PDF')->assertSee('data-official-page', false)
                ->assertSee('class="official-toolbar no-print"', false);
        }
    }

    public function test_a_school_without_a_logo_prints_a_blank_logo_area_not_another_schools_logo(): void
    {
        [, $school, $user] = $this->tenant();
        $this->actingAs($user);

        [, $right] = OfficialDocument::logos($school, null);
        $this->assertNull($right);
        $this->assertFileDoesNotExist(public_path('images/official-school-logo.png'));
    }

    public function test_the_school_profile_logo_is_used_when_the_file_exists(): void
    {
        Storage::fake('public');
        [, $school, $user] = $this->tenant();
        $this->actingAs($user);
        $path = UploadedFile::fake()->create('logo.png', 4, 'image/png')->store('logos', 'public');
        $school->update(['logo_path' => $path]);

        [, $right] = OfficialDocument::logos($school->fresh(), null);
        $this->assertStringEndsWith('storage/'.$path, $right);

        $school->update(['logo_path' => 'logos/missing.png']);
        [, $right] = OfficialDocument::logos($school->fresh(), null);
        $this->assertNull($right, 'a profile pointing at a missing file must not print a broken image');
    }
}
