<?php

namespace App\Services;

use App\Models\School;

/**
 * The one place the page settings of every printed document come from: the paper, the margins (PrintSettings) and the optional table and text style of a school.
 * The print pages read them through the print-settings JSON of partials/print-clean; the page layout and the pagination run in the browser (partials/print-pages).
 */
class DocumentPrintManager
{
    /**
     * The style fields a school can set, each with its label, unit, bounds and the CSS it drives. A field left empty changes nothing: the forms keep their own look.
     *
     * @var array<string, array{label: string, unit: string, min: float, max: float, step: float}>
     */
    public const STYLE = [
        'cell_padding' => ['label' => 'Table cell padding', 'unit' => 'mm', 'min' => 0, 'max' => 5, 'step' => 0.1],
        'text_size' => ['label' => 'Table text size', 'unit' => 'pt', 'min' => 6, 'max' => 14, 'step' => 0.5],
        'line_height' => ['label' => 'Line height', 'unit' => '×', 'min' => 1, 'max' => 2, 'step' => 0.05],
        'border_width' => ['label' => 'Table border thickness', 'unit' => 'px', 'min' => 0.5, 'max' => 3, 'step' => 0.5],
        'header_space' => ['label' => 'Space above the page content', 'unit' => 'mm', 'min' => 0, 'max' => 20, 'step' => 0.5],
        'footer_space' => ['label' => 'Space above the page footer', 'unit' => 'mm', 'min' => 0, 'max' => 20, 'step' => 0.5],
    ];

    /** The fonts a school can choose (a list, never free text: the value goes into CSS). */
    public const FONTS = [
        'arial' => ['Arial', 'Arial, Helvetica, sans-serif'],
        'times' => ['Times New Roman', '"Times New Roman", Times, serif'],
        'calibri' => ['Calibri', 'Calibri, Carlito, Arial, sans-serif'],
        'verdana' => ['Verdana', 'Verdana, Geneva, sans-serif'],
        'courier' => ['Courier New', '"Courier New", Courier, monospace'],
    ];

    public function __construct(private readonly PrintSettings $settings) {}

    /** The optional style of the school: only the fields it has set. */
    public function style(?int $schoolId): array
    {
        $style = $schoolId ? School::withoutGlobalScopes()->whereKey($schoolId)->value('print_style') : null;
        $style = is_string($style) ? json_decode($style, true) : $style;

        return $this->clean(is_array($style) ? $style : []);
    }

    /** Saves the style of the school; an empty set removes it. Returns the saved style. */
    public function saveStyle(School $school, array $style): array
    {
        $style = $this->clean($style);
        School::withoutGlobalScopes()->whereKey($school->id)->update(['print_style' => $style === [] ? null : json_encode($style)]);

        return $style;
    }

    /** Everything a print page needs for the school: the margins, the paper and the style (the print-settings JSON). */
    public function forSchool(?int $schoolId): array
    {
        return $this->settings->forSchool($schoolId) + ['paper' => $this->settings->paper($schoolId), 'style' => $this->style($schoolId)];
    }

    /** Keeps only known fields with a value inside their bounds (and a known font). */
    public function clean(array $style): array
    {
        $clean = [];
        foreach (self::STYLE as $key => $rule) {
            $value = $style[$key] ?? null;
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }
            $clean[$key] = round(min($rule['max'], max($rule['min'], (float) $value)), 2);
        }
        if (isset($style['font']) && isset(self::FONTS[$style['font']])) {
            $clean['font'] = $style['font'];
        }

        return $clean;
    }

    /** The CSS of the style (only for the fields that are set; every value is a bounded number or a font of the list, so nothing typed reaches the stylesheet). */
    public function css(array $style): string
    {
        $style = $this->clean($style);
        $page = '[data-official-page]';
        $notSvg = ':not(svg):not(svg *)';
        $rules = [];
        if (isset($style['font'])) {
            $rules[] = "{$page} *{$notSvg}{font-family:".self::FONTS[$style['font']][1].' !important}';
        }
        if (isset($style['text_size'])) {
            $rules[] = "{$page} table td{font-size:{$style['text_size']}pt !important}";
        }
        if (isset($style['line_height'])) {
            $rules[] = "{$page} *{$notSvg}{line-height:{$style['line_height']} !important}";
        }
        if (isset($style['cell_padding'])) {
            $rules[] = "{$page} table td,{$page} table th{padding:{$style['cell_padding']}mm !important}";
        }
        if (isset($style['border_width'])) {
            $rules[] = "{$page} table,{$page} table td,{$page} table th{border-width:{$style['border_width']}px !important}";
        }
        if (isset($style['header_space'])) {
            $rules[] = "{$page}[data-sheets] > .doc-page{padding-top:{$style['header_space']}mm !important}";
        }

        return implode('
', $rules);
    }
}
