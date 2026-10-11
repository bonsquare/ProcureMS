<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the Part I catalog of a Procurement Service APP-CSE .xlsx workbook: the category headings and, under each, the items
 * (code in column B, description in C, unit in D, price catalogue in Z). It only reads; a bad file throws a RuntimeException.
 * A heading with no item of its own, directly followed by another heading ("Motor Vehicles" above "A. For the Exercise of the Executive Functions"),
 * is a group heading: it is kept as the `group_heading` of every item after it. Merged cells are not expanded: a category heading is merged across the whole row and must stay a single filled cell.
 */
class CseCatalogReader
{
    private const MAX_SHEET_BYTES = 12582912;

    private const MAX_RAW_ROWS = 20000;

    private const RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const COLUMN_CODE = 2;

    private const COLUMN_DESCRIPTION = 3;

    private const COLUMN_UNIT = 4;

    private const COLUMN_PRICE = 26;

    /** @return array<int, array{group_heading: ?string, category: string, code: string, description: string, unit: string, price: ?float, sort_order: int}> */
    public function read(string $path): array
    {
        foreach ($this->sheets($path) as $sheet) {
            $grid = $this->grid($sheet['xml'], $sheet['shared']);
            $start = $this->partRow($grid, '/^PART\s+I\b(?!I)/i');
            if ($start === null) {
                continue;
            }

            $items = $this->items($grid, $start);
            if ($items === []) {
                throw new RuntimeException('No catalog items were found under PART I.');
            }

            return $items;
        }

        throw new RuntimeException('This is not a Procurement Service APP-CSE workbook: no "PART I" row was found.');
    }

    /** @return array<int, array{group_heading: ?string, category: string, code: string, description: string, unit: string, price: ?float, sort_order: int}> */
    private function items(array $grid, int $start): array
    {
        $items = [];
        $category = '';
        $group = null;
        $pending = null;

        foreach ($grid as $rowNumber => $cells) {
            if ($rowNumber <= $start) {
                continue;
            }
            $first = $cells[1] ?? '';
            if (preg_match('/^PART\s+II\b/i', $first)) {
                break;
            }
            if (count($cells) === 1 && isset($cells[1]) && ! is_numeric($first)) {
                if ($pending !== null) {
                    $group = $pending;
                }
                $pending = $first;
                $category = $first;

                continue;
            }
            if (isset($cells[self::COLUMN_CODE], $cells[self::COLUMN_DESCRIPTION])) {
                $pending = null;
                $price = is_numeric($cells[self::COLUMN_PRICE] ?? null) ? round((float) $cells[self::COLUMN_PRICE], 2) : null;
                $items[] = [
                    'group_heading' => $group,
                    'category' => $category,
                    'code' => $cells[self::COLUMN_CODE],
                    'description' => $cells[self::COLUMN_DESCRIPTION],
                    'unit' => $cells[self::COLUMN_UNIT] ?? '',
                    'price' => $price !== null && $price > 0 ? $price : null,
                    'sort_order' => count($items) + 1,
                ];
            }
        }

        return $items;
    }

    private function partRow(array $grid, string $pattern): ?int
    {
        foreach ($grid as $rowNumber => $cells) {
            if (isset($cells[1]) && preg_match($pattern, $cells[1])) {
                return $rowNumber;
            }
        }

        return null;
    }

    /** @return array<int, array{xml: string, shared: array<int, string>}> */
    private function sheets(string $path): array
    {
        $zip = new ZipArchive;
        if (@$zip->open($path, ZipArchive::RDONLY) !== true || $zip->locateName('xl/workbook.xml') === false) {
            throw new RuntimeException('The file is not a valid Excel (.xlsx) workbook.');
        }

        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (str_starts_with($stat['name'], 'xl/worksheets/') || $stat['name'] === 'xl/sharedStrings.xml') {
                $bytes += $stat['size'];
            }
        }
        if ($bytes > self::MAX_SHEET_BYTES) {
            throw new RuntimeException('The workbook is too large to read.');
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
            $file = $relationships[(string) $sheet->attributes(self::RELS)['id']] ?? null;
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
            throw new RuntimeException('The file is not a valid Excel (.xlsx) workbook.');
        }

        return $element;
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string>> row number => column index (A = 1) => text, whitespace normalized, empty cells left out
     */
    private function grid(string $xml, array $shared): array
    {
        if (substr_count($xml, '<row ') > self::MAX_RAW_ROWS) {
            throw new RuntimeException('The sheet has too many rows to read.');
        }

        $grid = [];
        foreach ($this->xml($xml)->sheetData->row as $row) {
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
                $value = trim((string) preg_replace('/\s+/u', ' ', $value));
                if ($value !== '') {
                    $grid[$rowNumber][$column] = $value;
                }
            }
        }
        ksort($grid);

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
}
