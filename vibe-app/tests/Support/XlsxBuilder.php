<?php

namespace Tests\Support;

use ZipArchive;

/** Builds a minimal valid .xlsx for tests, so no real (private) workbook is ever stored in the repository. */
class XlsxBuilder
{
    /**
     * @param  array<int, array{name: string, hidden?: bool, cells: array<string, mixed>, merges?: array<int, string>}>  $sheets
     *                                                                                                                            A cell value is a string, a number, or ['f' => '=16000', 'v' => 16000] for a formula with its stored value.
     */
    public static function make(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_values($sheets) as $index => $sheet) {
            $n = $index + 1;
            $types .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbook .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1).'" sheetId="'.$n.'"'.(! empty($sheet['hidden']) ? ' state="hidden"' : '').' r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::sheetXml($sheet));
        }

        $zip->addFromString('[Content_Types].xml', $types.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $workbook.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
        $zip->close();

        return $path;
    }

    private static function sheetXml(array $sheet): string
    {
        $rows = [];
        foreach ($sheet['cells'] as $ref => $value) {
            if ($value === null) {
                continue;
            }
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $rows[(int) $m[2]][self::columnIndex($m[1])] = [$ref, $value];
        }
        ksort($rows);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowNumber => $cells) {
            ksort($cells);
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($cells as [$ref, $value]) {
                if (is_array($value)) {
                    $xml .= '<c r="'.$ref.'"><f>'.htmlspecialchars(ltrim((string) $value['f'], '='), ENT_XML1).'</f><v>'.$value['v'].'</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if (! empty($sheet['merges'])) {
            $xml .= '<mergeCells count="'.count($sheet['merges']).'">'.implode('', array_map(fn ($ref) => '<mergeCell ref="'.$ref.'"/>', $sheet['merges'])).'</mergeCells>';
        }

        return $xml.'</worksheet>';
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index;
    }
}
