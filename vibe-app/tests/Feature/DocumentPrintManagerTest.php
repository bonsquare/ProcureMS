<?php

namespace Tests\Feature;

use App\Http\Controllers\PrintSettingsController;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DocumentPrintManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The central print manager: the optional table and text style of a school, its CSS and the print test document. */
class DocumentPrintManagerTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    private function form(School $school, array $style): array
    {
        return ['school_id' => $school->id, 'default' => ['top' => '15', 'right' => '10', 'bottom' => '15', 'left' => '10'], 'style' => $style];
    }

    public function test_clean_keeps_known_fields_inside_their_bounds_and_drops_the_rest(): void
    {
        $clean = app(DocumentPrintManager::class)->clean(['cell_padding' => '99', 'text_size' => '1', 'line_height' => '', 'font' => 'comic', 'bogus' => '5']);

        $this->assertSame(['cell_padding' => 5.0, 'text_size' => 6.0], $clean);
        $this->assertSame(['font' => 'times'], app(DocumentPrintManager::class)->clean(['font' => 'times']));
    }

    public function test_css_is_empty_when_nothing_is_set_and_never_carries_typed_text(): void
    {
        $manager = app(DocumentPrintManager::class);

        $this->assertSame('', $manager->css([]));
        $css = $manager->css(['cell_padding' => 1.5, 'border_width' => 2, 'header_space' => 4, 'font' => 'times', 'text_size' => '};alert(1)']);
        $this->assertStringContainsString('padding:1.5mm', $css);
        $this->assertStringContainsString('border-width:2px', $css);
        $this->assertStringContainsString('padding-top:4mm', $css);
        $this->assertStringContainsString('Times New Roman', $css);
        $this->assertStringNotContainsString('alert', $css);
    }

    public function test_the_style_is_saved_recorded_and_removed_when_emptied(): void
    {
        [$school, $user] = $this->tenant('manager-a');
        $manager = app(DocumentPrintManager::class);

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['cell_padding' => '1.2', 'font' => 'arial', 'line_height' => '']))->assertSessionHasNoErrors();
        $this->assertSame(['cell_padding' => 1.2, 'font' => 'arial'], $manager->style($school->id));
        $log = AuditLog::where('action', 'print_settings_changed')->latest('id')->firstOrFail();
        $this->assertSame([], $log->metadata['before']['style']);
        $this->assertSame(1.2, (float) $log->metadata['after']['style']['cell_padding']);

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, []))->assertSessionHasNoErrors();
        $this->assertSame([], $manager->style($school->id));
        $this->assertNull(School::withoutGlobalScopes()->find($school->id)->print_style);
    }

    public function test_a_style_outside_its_bounds_or_with_an_unknown_font_is_refused(): void
    {
        [$school, $user] = $this->tenant('manager-b');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['cell_padding' => '50']))->assertSessionHasErrors('style.cell_padding');
        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['font' => 'comic']))->assertSessionHasErrors('style.font');
    }

    public function test_the_print_test_document_renders_each_scenario_for_a_user_with_the_permission(): void
    {
        [, $user] = $this->tenant('manager-c');

        foreach (array_keys(PrintSettingsController::TEST_SCENARIOS) as $scenario) {
            $this->actingAs($user)->get(route('school-settings.printing.test-document', ['scenario' => $scenario, 'orientation' => 'landscape']))
                ->assertOk()->assertSee('PRINT TEST DOCUMENT')->assertSee('data-paginate', false);
        }
        $this->actingAs($user)->get(route('school-settings.printing.test-document', ['scenario' => 'nope']))->assertOk()->assertSee('Long table (60 rows)');
    }

    public function test_the_print_test_document_needs_the_settings_permission(): void
    {
        [, $user] = $this->tenant('manager-d', 'cashier');

        $this->actingAs($user)->get(route('school-settings.printing.test-document'))->assertForbidden();
    }

    public function test_the_printing_tab_offers_the_test_document_and_the_style_boxes(): void
    {
        [$school, $user] = $this->tenant('manager-e');

        $this->actingAs($user)->get(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'printing']))
            ->assertOk()->assertSee('Print test document')->assertSee('Table and text style');
    }
}
