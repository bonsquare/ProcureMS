<?php

namespace Tests\Feature;

use App\Services\SipImportService;
use App\Services\SipWorkbookReader;
use Tests\Support\XlsxBuilder;
use Tests\TestCase;

class SipWorkbookReaderTest extends TestCase
{
    /** A sheet laid out like the official SIP workbook. $override replaces or (with null) removes cells. */
    private function sipCells(array $override = [], int $shift = 0): array
    {
        $col = fn (string $letter) => chr(ord($letter) + $shift);
        $cells = [
            'B8' => 'SCHOOL IMPROVEMENT PLAN', 'B9' => 'FY 2026-2028', 'B10' => 'LUBAS ELEMENTARY SCHOOL',
            'B12' => 'Pillar (Access, Equity, Quality, Resiliency, Well-Being, Enabling Mechanism)', 'C12' => 'KRA', 'D12' => 'DepEd Organizational Outcomes', 'E12' => 'Strategy (Processes)', 'F12' => '5-Point Agenda',
            'G12' => 'Specific Program / Project', 'H12' => 'Activity', 'I12' => 'Physical Targets', 'L12' => 'Financial Target', 'O12' => "Source of Fund\r\n(School: MOOE, SEF, IGP)", 'P12' => 'Responsible Person', 'Q12' => 'Remarks (Important Notes)',
            'I13' => 'YEAR 1', 'J13' => 'YEAR 2', 'K13' => 'YEAR 3', 'L13' => 'YEAR 1', 'M13' => 'YEAR 2', 'N13' => 'YEAR 3',
            // Program One: pillar merged B14:B17, program-level cells only on its first row
            'B14' => 'ACCESS', 'C14' => 'KRA 3: Learner Formation', 'D14' => 'Outcome A', 'E14' => 'Strategy A', 'F14' => 'Agenda A', 'G14' => 'Program One', 'H14' => 'Activity 1', 'I14' => 1, 'J14' => 1, 'K14' => 1, 'L14' => 500, 'M14' => 500, 'N14' => 500, 'O14' => 'MOOE', 'P14' => 'Head',
            'H15' => 'Activity 2', 'I15' => 1, 'J15' => 1, 'K15' => 1, 'L15' => ['f' => '=16000', 'v' => 16000], 'M15' => 100, 'N15' => 100, 'P15' => 'Head',
            'H16' => 'Activity 3', 'I16' => 2, 'L16' => '1,500', 'M16' => '₱2,000.00', 'P16' => 'Team',
            'C17' => 'KRA 4: School Operations', 'D17' => 'Outcome B', 'E17' => 'Strategy B', 'F17' => 'Agenda B', 'G17' => 'Program Two', 'H17' => 'Activity 4', 'I17' => 1, 'L17' => 250, 'O17' => 'SEF', 'P17' => 'Head',
            'B18' => 'Well-Being and Resilience', 'C18' => 'KRA 1: School Leadership', 'D18' => 'Outcome C', 'E18' => 'Strategy C', 'F18' => 'Agenda C', 'G18' => 'Program Three', 'H18' => 'Activity 5', 'I18' => 1, 'L18' => 100, 'O18' => 'IGP', 'P18' => 'Head',
            'B19' => 'TOTAL', 'L19' => 99999,
            'B21' => 'Prepared by:', 'E21' => 'Recommending Approval:', 'J21' => 'Approved by:',
            'C23' => 'ANA HEAD', 'E23' => 'BEN CHIEF', 'M23' => 'CY SUPER',
            'C24' => 'School Head', 'E24' => 'Chief, SGOD', 'M24' => 'Schools Division Superintendent',
        ];
        if ($shift) {
            $shifted = [];
            foreach ($cells as $ref => $value) {
                preg_match('/^([A-Z])(\d+)$/', $ref, $m);
                $shifted[$col($m[1]).$m[2]] = $value;
            }
            $cells = $shifted;
        }

        return array_filter($override + $cells, fn ($value) => $value !== null);
    }

    private function book(array $cells, array $merges = ['I12:K12', 'L12:N12', 'B14:B17'], array $extraSheets = []): string
    {
        return XlsxBuilder::make([
            ['name' => 'ACCESS', 'cells' => ['B2' => 'Pillar sheet with no SIP table']],
            ['name' => 'school Improvement Plan', 'cells' => $cells, 'merges' => $merges],
            ['name' => 'governance', 'hidden' => true, 'cells' => ['B12' => 'Specific Program / Project', 'C12' => 'Activity', 'D12' => 'Physical Targets', 'B14' => 'Decoy']],
            ...$extraSheets,
        ]);
    }

    private function read(string $path): array
    {
        $result = app(SipWorkbookReader::class)->read($path);
        @unlink($path);

        return $result;
    }

    /** The preview shows what the reader could not read plus what the shared validation rejects. */
    private function errors(array $result): array
    {
        $issues = $result['issues'];
        if ($result['plan']['projects'] !== []) {
            $issues = array_merge($issues, app(SipImportService::class)->validate($result['plan']));
        }

        return array_values(array_filter($issues, fn ($issue) => $issue['level'] === 'error'));
    }

    public function test_it_reads_the_sip_sheet_and_ignores_other_sheets(): void
    {
        $result = $this->read($this->book($this->sipCells()));

        $this->assertSame([], $this->errors($result));
        $this->assertSame(['Program One', 'Program Two', 'Program Three'], array_column($result['plan']['projects'], 'project'));
        $this->assertCount(5, array_merge(...array_column($result['plan']['projects'], 'activities')));
        $this->assertNotContains('Decoy', array_column($result['plan']['projects'], 'project'));
    }

    public function test_it_finds_columns_by_header_text_not_by_letter(): void
    {
        $result = $this->read($this->book($this->sipCells([], 1), ['J12:L12', 'M12:O12', 'C14:C17']));

        $this->assertSame([], $this->errors($result));
        $this->assertSame('Program One', $result['plan']['projects'][0]['project']);
        $this->assertSame([500.0, 500.0, 500.0], $result['plan']['projects'][0]['activities'][0]['financial']);
    }

    public function test_it_fills_down_merged_and_program_level_cells(): void
    {
        $projects = $this->read($this->book($this->sipCells()))['plan']['projects'];

        $one = $projects[0];
        $this->assertSame('Access', $one['pillar']);
        $this->assertSame('KRA 3: Learner Formation', $one['kra']);
        $this->assertSame('MOOE', $one['source_of_fund']);
        $this->assertCount(3, $one['activities']);
        $this->assertSame('Access', $projects[1]['pillar']);
        $this->assertSame('KRA 4: School Operations', $projects[1]['kra']);
        $this->assertSame('SEF', $projects[1]['source_of_fund']);
        $this->assertSame(14, $one['row']);
        $this->assertSame(16, $one['activities'][2]['row']);
    }

    public function test_it_reads_formula_cells_by_their_stored_value(): void
    {
        $activities = $this->read($this->book($this->sipCells()))['plan']['projects'][0]['activities'];

        $this->assertSame(16000.0, $activities[1]['financial'][0]);
    }

    public function test_numbers_blank_text_and_bad_values(): void
    {
        $result = $this->read($this->book($this->sipCells()));
        $third = $result['plan']['projects'][0]['activities'][2];
        $this->assertSame([2.0, null, null], $third['physical']);
        $this->assertSame([1500.0, 2000.0, 0.0], $third['financial']);

        $bad = $this->read($this->book($this->sipCells(['L14' => 'abc', 'M14' => -5])));
        $messages = collect($this->errors($bad));
        $this->assertTrue($messages->contains(fn ($i) => $i['row'] === 14 && str_contains($i['message'], 'abc')));
        $this->assertTrue($messages->contains(fn ($i) => $i['row'] === 14 && str_contains($i['message'], '-5')));
    }

    public function test_pillar_text_is_mapped_to_the_apps_pillars(): void
    {
        $result = $this->read($this->book($this->sipCells(['B17' => null, 'B18' => 'Well-Being and Resilience'])));
        $this->assertSame(['Access', 'Access', 'Well-Being'], array_column($result['plan']['projects'], 'pillar'));

        foreach (['equity' => 'Equity', 'QUALITY' => 'Quality', 'Resiliency' => 'Resiliency', 'Enabling Mechanisms Governance' => 'Enabling Mechanism'] as $text => $expected) {
            $mapped = $this->read($this->book($this->sipCells(['B18' => $text])));
            $this->assertSame($expected, $mapped['plan']['projects'][2]['pillar'], $text);
        }

        $unknown = $this->read($this->book($this->sipCells(['B18' => 'Banana'])));
        $this->assertTrue(collect($this->errors($unknown))->contains(fn ($i) => $i['row'] === 18 && str_contains($i['message'], 'Banana')));
    }

    public function test_plan_period_and_school_name_come_from_the_title(): void
    {
        $plan = $this->read($this->book($this->sipCells()))['plan']['plan'];
        $this->assertSame(2026, $plan['start_year']);
        $this->assertSame('2026-2028', $plan['planning_period']);
        $this->assertSame('LUBAS ELEMENTARY SCHOOL', $plan['school_name']);

        $missing = $this->read($this->book($this->sipCells(['B9' => 'FY unknown'])));
        $this->assertTrue(collect($this->errors($missing))->contains(fn ($i) => str_contains($i['message'], 'FY')));
    }

    public function test_signatories_are_matched_left_to_right(): void
    {
        $result = $this->read($this->book($this->sipCells()));
        $this->assertSame([
            'prepared_by_name' => 'ANA HEAD', 'prepared_by_position' => 'School Head',
            'recommended_by_name' => 'BEN CHIEF', 'recommended_by_position' => 'Chief, SGOD',
            'approved_by_name' => 'CY SUPER', 'approved_by_position' => 'Schools Division Superintendent',
        ], $result['plan']['signatories']);

        $two = $this->read($this->book($this->sipCells(['M23' => null, 'M24' => null])));
        $this->assertSame('', (string) $two['plan']['signatories']['approved_by_name']);
        $this->assertTrue(collect($two['issues'])->contains(fn ($i) => $i['level'] === 'warning' && str_contains($i['message'], 'signator')));
    }

    public function test_total_rows_and_empty_rows_are_skipped(): void
    {
        $result = $this->read($this->book($this->sipCells()));

        $this->assertSame([], $this->errors($result));
        $this->assertSame(5, count(array_merge(...array_column($result['plan']['projects'], 'activities'))));
    }

    public function test_a_row_with_numbers_but_no_activity_text_is_an_error(): void
    {
        $result = $this->read($this->book($this->sipCells(['H15' => null])));

        $this->assertTrue(collect($this->errors($result))->contains(fn ($i) => $i['row'] === 15 && str_contains(strtolower($i['message']), 'activity')));
    }

    public function test_a_program_without_a_kra_on_its_first_row_is_reported(): void
    {
        $result = $this->read($this->book($this->sipCells(['C17' => null])));

        $this->assertTrue(collect($this->errors($result))->contains(fn ($i) => $i['row'] === 17 && str_contains($i['message'], 'KRA')));
    }

    public function test_not_a_real_xlsx_gives_an_error_issue(): void
    {
        $text = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        file_put_contents($text, 'this is not a workbook');
        $zipWithoutWorkbook = tempnam(sys_get_temp_dir(), 'zip').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($zipWithoutWorkbook, \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'hi');
        $zip->close();

        foreach ([$text, $zipWithoutWorkbook] as $path) {
            $result = $this->read($path);
            $this->assertSame([], $result['plan']['projects']);
            $this->assertNotEmpty($this->errors($result));
        }
    }

    public function test_no_sip_table_gives_an_error_issue(): void
    {
        $result = $this->read(XlsxBuilder::make([['name' => 'Other', 'cells' => ['A1' => 'Nothing here']]]));

        $this->assertSame([], $result['plan']['projects']);
        $this->assertTrue(collect($this->errors($result))->contains(fn ($i) => str_contains($i['message'], 'No SIP table')));
    }

    public function test_html_in_cells_is_kept_as_plain_text(): void
    {
        $result = $this->read($this->book($this->sipCells(['H14' => '<script>alert(1)</script>'])));

        $this->assertSame('<script>alert(1)</script>', $result['plan']['projects'][0]['activities'][0]['activity']);
    }

    public function test_more_than_5000_data_rows_are_refused(): void
    {
        // Extra data rows above the signature block, which is moved far down.
        $cells = $this->sipCells(['B21' => null, 'E21' => null, 'J21' => null, 'C23' => null, 'E23' => null, 'M23' => null, 'C24' => null, 'E24' => null, 'M24' => null, 'B9000' => 'Prepared by:', 'C9002' => 'ANA']);
        for ($row = 30; $row < 30 + 5001; $row++) {
            $cells['H'.$row] = 'Activity '.$row;
            $cells['L'.$row] = 1;
        }

        $result = $this->read($this->book($cells));

        $this->assertSame([], $result['plan']['projects']);
        $this->assertTrue(collect($this->errors($result))->contains(fn ($i) => str_contains($i['message'], '5,000')));
    }

    public function test_a_program_cell_merged_down_its_activities_is_one_program(): void
    {
        $merges = ['I12:K12', 'L12:N12', 'B14:B17', 'G14:G16', 'C14:C16'];
        $result = $this->read($this->book($this->sipCells(), $merges));

        $this->assertSame(['Program One', 'Program Two', 'Program Three'], array_column($result['plan']['projects'], 'project'));
        $this->assertCount(3, $result['plan']['projects'][0]['activities']);
        $this->assertSame([], $this->errors($result));
    }

    public function test_heading_rows_repeated_in_the_middle_of_the_sheet_are_skipped(): void
    {
        $repeated = ['B19' => null, 'L19' => null, 'G19' => 'Specific Program / Project', 'H19' => 'Activity', 'I19' => 'Physical Targets', 'L20' => 'YEAR 1', 'M20' => 'YEAR 2', 'N20' => 'YEAR 3', 'I20' => 'YEAR 1', 'J20' => 'YEAR 2', 'K20' => 'YEAR 3', 'M19' => 'Financial Target'];
        $result = $this->read($this->book($this->sipCells($repeated)));

        $this->assertSame([], $this->errors($result));
        $this->assertSame(5, count(array_merge(...array_column($result['plan']['projects'], 'activities'))));
    }

    public function test_merged_signatory_cells_count_once(): void
    {
        $merges = ['I12:K12', 'L12:N12', 'B14:B17', 'C23:D23', 'E23:F23', 'M23:N23', 'C24:D24', 'E24:F24', 'M24:N24'];
        $result = $this->read($this->book($this->sipCells(), $merges));

        $this->assertSame(['ANA HEAD', 'BEN CHIEF', 'CY SUPER'], [$result['plan']['signatories']['prepared_by_name'], $result['plan']['signatories']['recommended_by_name'], $result['plan']['signatories']['approved_by_name']]);
        $this->assertSame('Schools Division Superintendent', $result['plan']['signatories']['approved_by_position']);
    }
}
