<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PrintSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Printing tab of School Settings: the default margins and the margins of one document. */
class PrintSettingsTabTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    private function tab(School $school): string
    {
        return route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'printing']);
    }

    public function test_the_printing_tab_shows_the_default_and_every_document(): void
    {
        [$school, $user] = $this->tenant('tab-a');

        $page = $this->actingAs($user)->get($this->tab($school))->assertOk()->assertSee('Printing')->assertSee('name="default[top]"', false)->assertSee('name="default[left]"', false)
            ->assertSee('data-guard="Save the print settings"', false)->assertSee('13 mm');

        foreach (array_keys(PrintSettings::DOCUMENTS) as $key) {
            $page->assertSee('name="documents['.$key.'][top]"', false);
        }
        $page->assertSee(PrintSettings::DOCUMENTS['sob']);
    }

    public function test_saved_values_are_shown_in_the_boxes(): void
    {
        [$school, $user] = $this->tenant('tab-b');
        app(PrintSettings::class)->save($school, ['top' => 15, 'right' => 10, 'bottom' => 15, 'left' => 10], ['sob' => ['top' => 12, 'right' => 8, 'bottom' => 14, 'left' => 8]]);

        $this->actingAs($user)->get($this->tab($school))->assertOk()
            ->assertSee('name="default[top]" value="15"', false)->assertSee('name="documents[sob][bottom]" value="14"', false);
    }

    public function test_a_school_without_settings_shows_blank_document_boxes(): void
    {
        [$school, $user] = $this->tenant('tab-c');

        $this->actingAs($user)->get($this->tab($school))->assertOk()->assertSee('name="documents[sob][top]" value=""', false);
    }

    public function test_the_tab_is_offered_in_the_tab_bar(): void
    {
        [$school, $user] = $this->tenant('tab-d');

        $this->actingAs($user)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id]))->assertOk()->assertSee('tab=printing', false);
    }

    public function test_a_user_without_the_permission_sees_the_values_but_no_save_button(): void
    {
        [$school, $user] = $this->tenant('tab-e', 'auditor');

        $this->actingAs($user)->get($this->tab($school))->assertOk()->assertDontSee('data-guard="Save the print settings"', false);
    }

    public function test_a_refused_save_keeps_what_was_typed(): void
    {
        [$school, $user] = $this->tenant('tab-f');
        $url = $this->tab($school);

        $this->actingAs($user)->from($url)->put(route('school-settings.printing.update'), ['school_id' => $school->id, 'default' => ['top' => '41', 'right' => '10', 'bottom' => '15', 'left' => '10']])->assertRedirect($url);

        $this->get($url)->assertSee('name="default[top]" value="41"', false)->assertSee('name="default[right]" value="10"', false);
    }

    public function test_documents_that_ignore_the_default_are_marked(): void
    {
        [$school, $user] = $this->tenant('tab-g');

        $this->actingAs($user)->get($this->tab($school))->assertSee('Own row only');
    }

    public function test_the_paper_size_box_shows_the_saved_paper(): void
    {
        [$school, $user] = $this->tenant('tab-h');
        app(PrintSettings::class)->savePaper($school, 'folio');

        $this->actingAs($user)->get($this->tab($school))->assertOk()->assertSee('name="paper"', false)->assertSee('<option value="folio" selected>', false);
    }

    public function test_the_tab_can_fill_every_procurement_document_at_once(): void
    {
        [$school, $user] = $this->tenant('tab-d');

        $page = $this->actingAs($user)->get($this->tab($school))->assertOk()
            ->assertSee('All procurement documents at once')->assertSee('id="fill-procurement"', false)->assertSee('data-procurement-side="bottom"', false);

        // Every procurement key has a row to fill, and the fixed financial forms are not among them.
        foreach (PrintSettings::PROCUREMENT as $key) {
            $this->assertArrayHasKey($key, PrintSettings::DOCUMENTS);
            $page->assertSee('data-doc-row="'.$key.'"', false);
        }
        $this->assertNotContains('dv', PrintSettings::PROCUREMENT);
        $this->assertNotContains('ors', PrintSettings::PROCUREMENT);
        $this->assertNotContains('sob', PrintSettings::PROCUREMENT);
    }

    public function test_a_user_who_cannot_edit_does_not_get_the_fill_button(): void
    {
        [$school, $user] = $this->tenant('tab-e', 'school_staff');

        $page = $this->actingAs($user)->get($this->tab($school));

        if ($page->status() === 200) {
            $page->assertDontSee('id="fill-procurement"', false);
        } else {
            $page->assertForbidden();
        }
    }
}
