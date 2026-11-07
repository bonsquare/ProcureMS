<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PrintSetting;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PrintSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saving the print settings of a school: one default and optional margins for a document. */
class PrintSettingsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return [$school, User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role])];
    }

    private function set(float $top, float $right, float $bottom, float $left): array
    {
        return ['top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left];
    }

    private function form(School $school, array $extra = []): array
    {
        return array_replace(['school_id' => $school->id, 'default' => ['top' => '15', 'right' => '10', 'bottom' => '15', 'left' => '10']], $extra);
    }

    private function none(): array
    {
        return ['default' => null, 'documents' => []];
    }

    public function test_a_default_and_a_document_row_are_saved_and_recorded(): void
    {
        [$school, $user] = $this->tenant('endpoint-a');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['documents' => ['sob' => ['top' => '12', 'right' => '8', 'bottom' => '14', 'left' => '8']]]))
            ->assertRedirect(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'printing']))->assertSessionHasNoErrors()->assertSessionHas('success');

        $found = app(PrintSettings::class)->forSchool($school->id);
        $this->assertSame($this->set(15.0, 10.0, 15.0, 10.0), $found['default']);
        $this->assertSame($this->set(12.0, 8.0, 14.0, 8.0), $found['documents']['sob']);
        $log = AuditLog::where('action', 'print_settings_changed')->latest('id')->firstOrFail();
        $this->assertNull($log->metadata['before']['default']);
        $this->assertSame(15.0, (float) $log->metadata['after']['default']['top']);
    }

    public function test_blank_boxes_mean_use_the_default_not_zero(): void
    {
        [$school, $user] = $this->tenant('endpoint-b');
        $blank = ['top' => '', 'right' => '', 'bottom' => '', 'left' => ''];

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['documents' => ['sob' => $blank]]))->assertSessionHasNoErrors();
        $this->assertSame([], app(PrintSettings::class)->forSchool($school->id)['documents']);

        $this->put(route('school-settings.printing.update'), ['school_id' => $school->id, 'default' => $blank])->assertSessionHasNoErrors();
        $this->assertNull(app(PrintSettings::class)->forSchool($school->id)['default']);
    }

    public function test_a_half_filled_set_is_refused(): void
    {
        [$school, $user] = $this->tenant('endpoint-c');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['documents' => ['sob' => ['top' => '12', 'right' => '', 'bottom' => '', 'left' => '']]]))->assertSessionHasErrors('documents.sob');
        $this->assertSame($this->none(), app(PrintSettings::class)->forSchool($school->id));
    }

    public function test_a_value_out_of_range_or_not_a_number_is_refused_and_nothing_is_saved(): void
    {
        [$school, $user] = $this->tenant('endpoint-d');

        foreach (['41', '-1', 'abc'] as $bad) {
            $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['default' => ['top' => $bad, 'right' => '10', 'bottom' => '15', 'left' => '10']]))->assertSessionHasErrors('default.top');
        }
        $this->assertSame($this->none(), app(PrintSettings::class)->forSchool($school->id));
    }

    public function test_an_unknown_document_key_is_refused(): void
    {
        [$school, $user] = $this->tenant('endpoint-e');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school, ['documents' => ['nonsense' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1']]]))->assertSessionHasErrors('documents');
        $this->assertSame(0, PrintSetting::withoutGlobalScopes()->count());
    }

    public function test_a_viewer_without_the_permission_is_forbidden(): void
    {
        [$school, $user] = $this->tenant('endpoint-f', 'auditor');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($school))->assertForbidden();
        $this->assertSame(0, PrintSetting::withoutGlobalScopes()->count());
    }

    public function test_another_schools_settings_cannot_be_written(): void
    {
        [, $user] = $this->tenant('endpoint-g');
        [$other] = $this->tenant('endpoint-h');

        $this->actingAs($user)->put(route('school-settings.printing.update'), $this->form($other))->assertStatus(404);
        $this->assertSame($this->none(), app(PrintSettings::class)->forSchool($other->id));
    }

    public function test_the_paper_of_the_school_is_saved_cleared_and_recorded(): void
    {
        [$school, $user] = $this->tenant('endpoint-p');

        $this->actingAs($user)->put(route('school-settings.printing.update'), ['school_id' => $school->id, 'paper' => 'folio'])->assertSessionHasNoErrors();
        $this->assertSame('folio', app(PrintSettings::class)->paper($school->id));
        $this->assertSame('folio', AuditLog::where('action', 'print_settings_changed')->latest('id')->firstOrFail()->metadata['after']['paper']);

        $this->put(route('school-settings.printing.update'), ['school_id' => $school->id, 'paper' => ''])->assertSessionHasNoErrors();
        $this->assertNull(app(PrintSettings::class)->paper($school->id));
    }

    public function test_an_unknown_paper_is_refused(): void
    {
        [$school, $user] = $this->tenant('endpoint-q');

        $this->actingAs($user)->put(route('school-settings.printing.update'), ['school_id' => $school->id, 'paper' => 'poster'])->assertSessionHasErrors('paper');
        $this->assertNull(app(PrintSettings::class)->paper($school->id));
    }
}
