<?php

namespace Tests\Support;

/** A SIP sheet laid out like the official workbook, for tests. */
class SipSheet
{
    /** Cells of the sample sheet. $override replaces or (with null) removes cells; $shift moves every column right. */
    public static function cells(array $override = [], int $shift = 0): array
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

    /** A workbook with a decoy pillar sheet, the SIP sheet and a hidden decoy. */
    public static function book(array $cells, array $merges = ['I12:K12', 'L12:N12', 'B14:B17'], array $extraSheets = []): string
    {
        return XlsxBuilder::make([
            ['name' => 'ACCESS', 'cells' => ['B2' => 'Pillar sheet with no SIP table']],
            ['name' => 'school Improvement Plan', 'cells' => $cells, 'merges' => $merges],
            ['name' => 'governance', 'hidden' => true, 'cells' => ['B12' => 'Specific Program / Project', 'C12' => 'Activity', 'D12' => 'Physical Targets', 'B14' => 'Decoy']],
            ...$extraSheets,
        ]);
    }
}
