<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PrintSetting;
use App\Models\School;
use App\Services\PrintSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The print settings of a school: one default set of margins and optional margins for a single document. */
class PrintSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug): School
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);

        return School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
    }

    private function set(float $top, float $right, float $bottom, float $left): array
    {
        return ['top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left];
    }

    public function test_a_school_without_rows_has_no_settings(): void
    {
        $school = $this->school('none');

        $this->assertSame(['default' => null, 'documents' => []], app(PrintSettings::class)->forSchool($school->id));
        $this->assertSame(['default' => null, 'documents' => []], app(PrintSettings::class)->forSchool(null));
        $this->assertNull(app(PrintSettings::class)->pageMargin($school->id));
    }

    public function test_save_stores_a_default_and_a_document_row(): void
    {
        $school = $this->school('save');

        app(PrintSettings::class)->save($school, $this->set(15, 10, 15, 10), ['sob' => $this->set(12, 8, 14, 8)]);

        $found = app(PrintSettings::class)->forSchool($school->id);
        $this->assertSame($this->set(15.0, 10.0, 15.0, 10.0), $found['default']);
        $this->assertSame(['sob' => $this->set(12.0, 8.0, 14.0, 8.0)], $found['documents']);
        $this->assertSame($this->set(15.0, 10.0, 15.0, 10.0), app(PrintSettings::class)->pageMargin($school->id));
    }

    public function test_save_replaces_and_removes_rows_and_reports_before_and_after(): void
    {
        $school = $this->school('replace');
        app(PrintSettings::class)->save($school, $this->set(15, 10, 15, 10), ['sob' => $this->set(12, 8, 14, 8)]);

        $change = app(PrintSettings::class)->save($school, $this->set(20, 20, 20, 20), []);

        $found = app(PrintSettings::class)->forSchool($school->id);
        $this->assertSame($this->set(20.0, 20.0, 20.0, 20.0), $found['default']);
        $this->assertSame([], $found['documents']);
        $this->assertSame(1, PrintSetting::whereNull('doc_key')->count(), 'the default row is updated, never doubled');
        $this->assertSame($this->set(15.0, 10.0, 15.0, 10.0), $change['before']['default']);
        $this->assertSame($this->set(20.0, 20.0, 20.0, 20.0), $change['after']['default']);

        app(PrintSettings::class)->save($school, null, []);
        $this->assertSame(['default' => null, 'documents' => []], app(PrintSettings::class)->forSchool($school->id));
    }

    public function test_settings_are_kept_per_school(): void
    {
        $first = $this->school('first');
        $second = $this->school('second');

        app(PrintSettings::class)->save($first, $this->set(15, 10, 15, 10), ['ors' => $this->set(5, 5, 5, 5)]);

        $this->assertSame(['default' => null, 'documents' => []], app(PrintSettings::class)->forSchool($second->id));
    }

    public function test_the_catalogue_names_every_printable_document(): void
    {
        foreach (['aip', 'alphalist', 'app', 'app-cse', 'cash-bank-recon', 'cash-checks-advices', 'cash-register', 'cash-roci', 'dv', 'ors', 'pow', 'pr', 'reconciliation', 'sip', 'sob', 'rfq', 'po', 'ntp', 'noa', 'abstract', 'iar', 'ris', 'iars', 'ics', 'generic'] as $key) {
            $this->assertArrayHasKey($key, PrintSettings::DOCUMENTS);
        }
    }
}
