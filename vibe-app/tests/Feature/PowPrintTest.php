<?php

namespace Tests\Feature;

use App\Models\PowPlan;
use App\Services\PowService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The printed Program of Works follows the owner's workbook on the shared official print engine, folio by default. */
class PowPrintTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: PowPlan} a POW with materials 55,000.00 and labor 19,250.00 */
    private function savedPow(array $over = []): array
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['school']->update(['address' => 'Sitio Lubas, Amabil, Magpet, Cotabato', 'region' => 'Region XII', 'division' => 'Cotabato']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 3, 'MOOE', $fx['user']);
        $pow = app(PowService::class)->create($plan, $over + [
            'program' => 'Program One', 'project_title' => 'Installation of CCTV', 'labor_cost' => 19250, 'pow_date' => '2026-08-10', 'date_started' => '2026-09-07', 'date_completed' => '2026-09-17',
            'lines' => [
                ['description' => '16-Channel Digital Video Recorder (DVR)', 'unit' => 'pcs', 'quantity' => 1, 'unit_cost' => 8800],
                ['description' => '5mp Bullet CCTV Camera', 'unit' => 'pcs', 'quantity' => 8, 'unit_cost' => 5775],
            ],
            'signatories' => ['prepared_by' => ['name' => 'Baby Joy I. Carbonell', 'position' => 'School Head'], 'recommended_by' => ['name' => 'Engr. Ma. Nora De Leon', 'position' => 'DepEd Project Engineer'], 'approved_by' => ['name' => 'Nesty Joy T. Pello, CE', 'position' => 'Division Engineer']],
        ], $fx['user']);

        return [$fx, $pow];
    }

    private function printed(PowPlan $pow): string
    {
        return $this->get(route('planning.pow.print', $pow))->assertOk()->getContent();
    }

    public function test_the_print_uses_the_official_engine_with_folio_as_default_paper(): void
    {
        [$fx, $pow] = $this->savedPow();

        $html = $this->printed($pow);

        $this->assertStringContainsString('data-official-page', $html);
        $this->assertStringContainsString('data-paper="folio"', $html);
        $this->assertStringContainsString('data-orientation="portrait"', $html);
        $this->assertSame(substr_count(view('partials.print-clean')->render(), '@page'), substr_count($html, '@page'), 'the form declares no @page of its own, only the shared engine does');
    }

    public function test_the_print_shows_letterhead_info_block_table_and_totals(): void
    {
        [$fx, $pow] = $this->savedPow();

        $html = $this->printed($pow);

        foreach ([
            'Republic of the Philippines', 'Department of Education', 'Sitio Lubas, Amabil, Magpet, Cotabato', 'PROGRAM OF WORKS', 'MOOE 3RD QUARTER 2026',
            'Installation of CCTV', 'August 10, 2026', 'September 7, 2026', 'September 17, 2026', 'Minimum Required Manpower', 'Minimum Required Equipment',
            'Item Description', 'A. Materials:', '16-Channel Digital Video Recorder (DVR)', '8,800.00', 'TOTAL MATERIALS COST', '55,000.00',
            'B. Labor Cost:', '19,250.00', 'C. Total Project Cost', '74,250.00', 'Budget Allocation',
        ] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertSame(2, substr_count($html, 'class="item-row"'), 'only the saved lines are numbered, no blank rows');
        $this->assertSame(2, substr_count($html, '74,250.00'), 'Budget Allocation and Total Project Cost');
    }

    public function test_the_deped_logo_is_on_the_left_and_the_school_logo_on_the_right(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/school.png', 'png');
        [$fx, $pow] = $this->savedPow();
        $fx['school']->update(['logo_path' => 'logos/school.png']);

        $html = $this->printed($pow);

        $this->assertMatchesRegularExpression('/class="masthead-logo masthead-logo-right"><img class="official-logo" src="[^"]*logos\/school\.png"/', $html);
        $this->assertLessThan(strpos($html, 'Republic of the Philippines'), strpos($html, 'class="masthead-logo masthead-logo-left"'), 'the DepEd logo comes before the title block');
        $this->assertGreaterThan(strpos($html, 'Republic of the Philippines'), strpos($html, 'class="masthead-logo masthead-logo-right"'), 'the school logo after it');
    }

    public function test_the_school_id_is_the_deped_school_id_else_the_system_code(): void
    {
        [$fx, $pow] = $this->savedPow();

        $this->assertStringContainsString('<td class="label">School ID:</td><td>'.$fx['school']->code.'</td>', $this->printed($pow));

        $fx['school']->update(['deped_school_id' => '130059']);
        $this->assertStringContainsString('<td class="label">School ID:</td><td>130059</td>', $this->printed($pow), 'the label and the ID number are separate cells');
    }

    public function test_the_optional_superintendent_signature_prints_only_when_filled(): void
    {
        [$fx, $pow] = $this->savedPow();
        $html = $this->printed($pow);

        foreach (['Prepared by:', 'Recommending Approval:', 'APPROVED:', 'BABY JOY I. CARBONELL', 'ENGR. MA. NORA DE LEON', 'DepEd Project Engineer', 'NESTY JOY T. PELLO, CE', 'Division Engineer'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertSame(1, substr_count($html, 'APPROVED:'));

        $pow->update(['signatories' => array_merge($pow->signatories, ['division_superintendent' => ['name' => 'Isagani S. Dela Cruz, CESO V', 'position' => 'Schools Division Superintendent']])]);
        $html = $this->printed($pow->fresh());
        $this->assertSame(2, substr_count($html, 'APPROVED:'));
        $this->assertStringContainsString('ISAGANI S. DELA CRUZ, CESO V', $html);
    }

    public function test_signature_names_are_bold_and_underlined(): void
    {
        [$fx, $pow] = $this->savedPow();

        $this->assertMatchesRegularExpression('/\.sig-name\s*\{[^}]*font-weight:\s*bold[^}]*text-decoration:\s*underline/', $this->printed($pow));
    }

    public function test_the_print_is_not_available_to_another_school(): void
    {
        $stranger = SobFixture::make();
        [$fx, $pow] = $this->savedPow();

        $this->actingAs($stranger['user'])->get(route('planning.pow.print', $pow))->assertNotFound();
    }
}
