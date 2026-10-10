<?php

namespace App\Services;

use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the School Improvement Plan table of an .xlsx workbook into the normalized plan array used by SipImportService.
 * It only reads: no database, no files left behind. A bad file never throws; it comes back as an error issue.
 */
class SipWorkbookReader
{
    public const MAX_DATA_ROWS = 5000;

    private const MAX_SHEET_BYTES = 31457280;

    private const MAX_RAW_ROWS = 20000;

    private const RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const SIGNATORIES = ['prepared' => 'prepared_by', 'recommend' => 'recommended_by', 'approved' => 'approved_by'];

    /** @return array{plan: array<string, mixed>, issues: array<int, array{level: string, row: int|null, message: string}>} */
    public function read(string $path): array
    {
        $issues = [];
        $plan = $this->emptyPlan();

        try {
            $sheets = $this->visibleSheets($path);
            $best = null;
            foreach ($sheets as $sheet) {
                $grid = $this->grid($sheet['xml'], $sheet['shared']);
                $header = $this->headerRow($grid);
                if ($header !== null && ($best === null || count($grid) > count($best['grid']))) {
                    $best = ['grid' => $grid, 'header' => $header];
                }
            }
            if ($best === null) {
                throw new \RuntimeException('No SIP table found. The workbook needs a sheet with the columns Specific Program, Activity and Physical Targets.');
            }
            $this->parse($best['grid'], $best['header'], $plan, $issues);
        } catch (\RuntimeException $e) {
            $issues[] = ['level' => 'error', 'row' => null, 'message' => $e->getMessage()];
            $plan['projects'] = [];
        } catch (\Throwable $e) {
            report($e);
            $issues[] = ['level' => 'error', 'row' => null, 'message' => 'The file could not be read as an Excel (.xlsx) workbook.'];
            $plan['projects'] = [];
        }

        return ['plan' => $plan, 'issues' => $issues];
    }

    private function emptyPlan(): array
    {
        return [
            'plan' => ['start_year' => null, 'planning_period' => '', 'school_name' => null],
            'signatories' => array_fill_keys(['prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position'], null),
            'projects' => [],
        ];
    }

    /** @return array<int, array{xml: string, shared: array<int, string>}> */
    private function visibleSheets(string $path): array
    {
        $zip = new ZipArchive;
        if (@$zip->open($path, ZipArchive::RDONLY) !== true || $zip->locateName('xl/workbook.xml') === false) {
            throw new \RuntimeException('The file is not a valid Excel (.xlsx) workbook.');
        }

        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (str_starts_with($stat['name'], 'xl/worksheets/') || $stat['name'] === 'xl/sharedStrings.xml') {
                $bytes += $stat['size'];
            }
        }
        if ($bytes > self::MAX_SHEET_BYTES) {
            throw new \RuntimeException('The workbook is too large to import.');
        }

        $workbook = $this->xml((string) $zip->getFromName('xl/workbook.xml'));
        $relationships = [];
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            foreach ($this->xml($relsXml)->Relationship as $rel) {
                $target = (string) $rel['Target'];
                $relationships[(string) $rel['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            foreach ($this->xml($sharedXml)->si as $si) {
                $text = '';
                foreach ($si->t as $t) {
                    $text .= (string) $t;
                }
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $sheets = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            if (in_array((string) $sheet['state'], ['hidden', 'veryHidden'], true)) {
                continue;
            }
            $id = (string) $sheet->attributes(self::RELS)['id'];
            $file = $relationships[$id] ?? null;
            $xml = $file ? $zip->getFromName($file) : false;
            if ($xml !== false) {
                $sheets[] = ['xml' => $xml, 'shared' => $shared];
            }
        }
        $zip->close();

        return $sheets;
    }

    private function xml(string $xml): SimpleXMLElement
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($element === false) {
            throw new \RuntimeException('The file is not a valid Excel (.xlsx) workbook.');
        }

        return $element;
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string>> row number => column index => text (merged ranges filled in)
     */
    private function grid(string $xml, array $shared): array
    {
        if (substr_count($xml, '<row ') > self::MAX_RAW_ROWS) {
            throw new \RuntimeException('The sheet has too many rows to import.');
        }
        $sheet = $this->xml($xml);
        $grid = [];
        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                [$column, $rowNumber] = $this->reference((string) $cell['r']);
                $type = (string) $cell['t'];
                if ($type === 's') {
                    $value = $shared[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($cell->is->t as $t) {
                        $value .= (string) $t;
                    }
                    foreach ($cell->is->r as $run) {
                        $value .= (string) $run->t;
                    }
                } else {
                    $value = (string) $cell->v;
                }
                $value = trim(str_replace(["\r\n", "\r", "\n"], ' ', $value));
                if ($value !== '') {
                    $grid[$rowNumber][$column] = $value;
                }
            }
        }

        $filled = 0;
        foreach ($sheet->mergeCells->mergeCell ?? [] as $merge) {
            [$from, $to] = array_pad(explode(':', (string) $merge['ref']), 2, null);
            if ($to === null) {
                continue;
            }
            [$c1, $r1] = $this->reference($from);
            [$c2, $r2] = $this->reference($to);
            $value = $grid[$r1][$c1] ?? null;
            if ($value === null) {
                continue;
            }
            for ($r = $r1; $r <= $r2 && $filled < 100000; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if (! isset($grid[$r][$c])) {
                        $grid[$r][$c] = $value;
                        $filled++;
                    }
                }
            }
        }
        ksort($grid);
        foreach ($grid as &$cells) {
            ksort($cells);
        }

        return $grid;
    }

    /** @return array{int, int} column index (A = 1) and row number */
    private function reference(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($ref), $m);
        $column = 0;
        foreach (str_split($m[1] ?? 'A') as $letter) {
            $column = $column * 26 + (ord($letter) - 64);
        }

        return [$column, (int) ($m[2] ?? 0)];
    }

    /** The row holding the column headings: it names a specific program, an activity and physical targets. */
    private function headerRow(array $grid): ?int
    {
        foreach ($grid as $row => $cells) {
            $text = mb_strtolower(implode(' | ', $cells));
            if (str_contains($text, 'specific program') && str_contains($text, 'activity') && str_contains($text, 'physical')) {
                return $row;
            }
        }

        return null;
    }

    private function parse(array $grid, int $headerRow, array &$plan, array &$issues): void
    {
        $columns = $this->columns($grid[$headerRow]);
        foreach (['pillar' => 'Pillar', 'kra' => 'KRA', 'project' => 'Specific Program / Project', 'activity' => 'Activity', 'physical' => 'Physical Targets', 'financial' => 'Financial Target'] as $key => $label) {
            if (! isset($columns[$key])) {
                throw new \RuntimeException("The column \"{$label}\" was not found in the SIP table.");
            }
        }

        $this->title($grid, $headerRow, $plan);

        $dataStart = $headerRow + 1;
        if (isset($grid[$dataStart]) && preg_match('/year\s*1/i', implode(' ', $grid[$dataStart]))) {
            $dataStart++;
        }
        $signatureRow = null;
        foreach ($grid as $row => $cells) {
            if ($row >= $dataStart && str_contains(mb_strtolower(implode(' | ', $cells)), 'prepared by')) {
                $signatureRow = $row;
                break;
            }
        }
        $rows = array_filter($grid, fn ($cells, $row) => $row >= $dataStart && ($signatureRow === null || $row < $signatureRow), ARRAY_FILTER_USE_BOTH);
        if (count($rows) > self::MAX_DATA_ROWS) {
            throw new \RuntimeException('The sheet has more than 5,000 data rows, which is more than can be imported at once.');
        }

        $pillar = '';
        $current = null;
        foreach ($rows as $rowNumber => $cells) {
            $get = fn (string $key) => ($columns[$key] ?? null) !== null ? ($cells[$columns[$key]] ?? '') : '';
            $first = mb_strtolower((string) reset($cells));
            if (str_starts_with($first, 'total') || $this->isHeadingRow($cells, $columns)) {
                continue;
            }
            if ($get('pillar') !== '') {
                $pillar = $this->pillar($get('pillar'));
            }

            $physical = $this->numbers($cells, $columns['physical'], $rowNumber, 'Physical target', true, $issues);
            $financial = $this->numbers($cells, $columns['financial'], $rowNumber, 'Financial amount', false, $issues);
            $hasNumbers = count(array_filter($physical, fn ($v) => $v !== null)) > 0 || array_sum($financial) !== 0.0
                || $this->anyCell($cells, $columns['physical']) || $this->anyCell($cells, $columns['financial']);

            // A program cell merged down its activities repeats the same text on every row: only a different text starts a new program.
            if ($get('project') !== '' && ($current === null || $get('project') !== $plan['projects'][$current]['project'])) {
                $plan['projects'][] = [
                    'row' => $rowNumber, 'pillar' => $pillar, 'kra' => $get('kra'), 'organizational_outcome' => $get('outcome'), 'strategy' => $get('strategy'),
                    'five_point_agenda' => $get('agenda'), 'project' => $get('project'), 'source_of_fund' => $get('source'), 'activities' => [],
                ];
                $current = array_key_last($plan['projects']);
            } elseif ($current !== null && $plan['projects'][$current]['source_of_fund'] === '' && $get('source') !== '') {
                $plan['projects'][$current]['source_of_fund'] = $get('source');
            }

            $activity = $get('activity');
            if ($activity === '') {
                if ($hasNumbers) {
                    $issues[] = ['level' => 'error', 'row' => $rowNumber, 'message' => "Row {$rowNumber}: there are numbers but no Activity text."];
                }

                continue;
            }
            if ($current === null) {
                $issues[] = ['level' => 'error', 'row' => $rowNumber, 'message' => "Row {$rowNumber}: an activity appears before any Specific Program / Project."];

                continue;
            }
            $plan['projects'][$current]['activities'][] = ['row' => $rowNumber, 'activity' => $activity, 'physical' => $physical, 'financial' => array_map(fn ($v) => (float) $v, $financial), 'responsible_person' => $get('responsible'), 'remarks' => $get('remarks')];
        }

        $this->signatories($grid, $signatureRow, $plan, $issues);
    }

    /** @return array<string, int> column index by field; physical and financial point at their Year 1 column */
    private function columns(array $header): array
    {
        $rules = [
            'pillar' => ['pillar'], 'kra' => ['kra'], 'outcome' => ['outcome'], 'strategy' => ['strategy'], 'agenda' => ['5-point', 'five-point', 'five point'],
            'project' => ['specific program', 'program / project'], 'activity' => ['activity'], 'physical' => ['physical'], 'financial' => ['financial'],
            'source' => ['source of fund'], 'responsible' => ['responsible'], 'remarks' => ['remarks'],
        ];
        $columns = [];
        foreach ($header as $index => $text) {
            $text = mb_strtolower($text);
            foreach ($rules as $key => $needles) {
                if (isset($columns[$key])) {
                    continue;
                }
                foreach ($needles as $needle) {
                    if ($key === 'kra' ? str_starts_with($text, $needle) : str_contains($text, $needle)) {
                        $columns[$key] = $index;

                        continue 3;
                    }
                }
            }
        }

        return $columns;
    }

    /** "FY 2026-2028" gives the years; the first text after it, above the table, is the school name. */
    private function title(array $grid, int $headerRow, array &$plan): void
    {
        $fyRow = null;
        foreach ($grid as $row => $cells) {
            if ($row >= $headerRow) {
                break;
            }
            if (preg_match('/FY\s*(\d{4})\s*[-–]\s*(\d{4})/i', implode(' ', $cells), $m)) {
                $plan['plan']['start_year'] = (int) $m[1];
                $plan['plan']['planning_period'] = $m[1].'-'.$m[2];
                $fyRow = $row;
                break;
            }
        }
        if ($fyRow !== null) {
            foreach ($grid as $row => $cells) {
                if ($row > $fyRow && $row < $headerRow && $cells !== []) {
                    $plan['plan']['school_name'] = (string) reset($cells);
                    break;
                }
            }
        }
    }

    /** @return array<int, float|null> three values, Year 1 to 3 */
    private function numbers(array $cells, int $start, int $row, string $label, bool $nullable, array &$issues): array
    {
        $values = [];
        foreach ([0, 1, 2] as $offset) {
            $raw = trim((string) ($cells[$start + $offset] ?? ''));
            $year = $offset + 1;
            if ($raw === '') {
                $values[] = $nullable ? null : 0.0;

                continue;
            }
            $clean = str_replace([',', '₱', ' ', "\u{A0}", 'PHP', 'Php', 'php'], '', $raw);
            if (! is_numeric($clean)) {
                $issues[] = ['level' => 'error', 'row' => $row, 'message' => "Row {$row}: \"{$raw}\" in {$label} Year {$year} is not a number."];
                $values[] = $nullable ? null : 0.0;

                continue;
            }
            if ((float) $clean < 0) {
                $issues[] = ['level' => 'error', 'row' => $row, 'message' => "Row {$row}: \"{$raw}\" in {$label} Year {$year} must be 0 or more."];
            }
            $values[] = (float) $clean;
        }

        return $values;
    }

    /** The table heading printed again at a page break, and its Year 1 to 3 line, are not data. */
    private function isHeadingRow(array $cells, array $columns): bool
    {
        $text = mb_strtolower(implode(' | ', $cells));
        if (str_contains($text, 'specific program') || str_contains($text, 'physical target') || str_contains($text, 'financial target')) {
            return true;
        }
        $numeric = array_filter([$cells[$columns['physical']] ?? null, $cells[$columns['financial']] ?? null]);

        return $numeric !== [] && count(array_filter($numeric, fn ($value) => preg_match('/^year\s*\d$/i', $value))) === count($numeric);
    }

    /** A merged cell is copied into every cell it covers; a signatory is the cell and not its copies. */
    private function withoutMergedCopies(array $cells): array
    {
        $values = [];
        foreach (array_values($cells) as $value) {
            if ($values === [] || end($values) !== $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function anyCell(array $cells, int $start): bool
    {
        return isset($cells[$start]) || isset($cells[$start + 1]) || isset($cells[$start + 2]);
    }

    private function pillar(string $text): string
    {
        $lower = mb_strtolower($text);
        foreach (['access' => 'Access', 'equity' => 'Equity', 'quality' => 'Quality', 'well' => 'Well-Being', 'resilien' => 'Resiliency', 'enabling' => 'Enabling Mechanism'] as $needle => $pillar) {
            if (str_contains($lower, $needle)) {
                return $pillar;
            }
        }

        return $text;
    }

    /** Names are on the next non-empty row after the labels, positions on the one after; matched to the labels left to right. */
    private function signatories(array $grid, ?int $labelRow, array &$plan, array &$issues): void
    {
        if ($labelRow === null) {
            $issues[] = ['level' => 'warning', 'row' => null, 'message' => 'No signatories were found ("Prepared by", "Recommending Approval", "Approved by").'];

            return;
        }
        $below = array_values(array_filter(array_keys($grid), fn ($row) => $row > $labelRow));
        $namesRow = $below[0] ?? null;
        $positionsRow = $below[1] ?? null;
        $names = $namesRow ? $this->withoutMergedCopies($grid[$namesRow]) : [];
        $positions = $positionsRow ? $this->withoutMergedCopies($grid[$positionsRow]) : [];

        foreach (array_values(self::SIGNATORIES) as $i => $prefix) {
            $plan['signatories'][$prefix.'_name'] = $names[$i] ?? null;
            $plan['signatories'][$prefix.'_position'] = $positions[$i] ?? null;
        }
        if (count($names) < 3) {
            $issues[] = ['level' => 'warning', 'row' => $namesRow, 'message' => 'Only '.count($names).' of 3 signatories were found; fill in the missing ones after importing.'];
        }
    }
}
