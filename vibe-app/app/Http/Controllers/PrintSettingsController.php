<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Services\DocumentPrintManager;
use App\Services\PrintSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Saves the page margins of a school (the Printing tab of School Settings). */
class PrintSettingsController extends Controller
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    public function update(Request $request, PrintSettings $settings, DocumentPrintManager $manager): RedirectResponse
    {
        $margin = ['nullable', 'numeric', 'between:0,40'];
        $rules = ['school_id' => ['required', 'integer'], 'default' => ['nullable', 'array'], 'documents' => ['nullable', 'array', 'max:'.count(PrintSettings::DOCUMENTS)], 'documents.*' => ['array'], 'paper' => ['nullable', Rule::in(array_keys(PrintSettings::PAPERS))]];
        foreach (self::SIDES as $side) {
            $rules['default.'.$side] = $margin;
            $rules['documents.*.'.$side] = $margin;
        }
        $rules['style'] = ['nullable', 'array'];
        foreach (DocumentPrintManager::STYLE as $key => $rule) {
            $rules['style.'.$key] = ['nullable', 'numeric', 'between:'.$rule['min'].','.$rule['max']];
        }
        $rules['style.font'] = ['nullable', Rule::in(array_keys(DocumentPrintManager::FONTS))];
        $data = $request->validate($rules);

        // The school of the user's organization (another organization's school is not found).
        $school = School::findOrFail((int) $data['school_id']);
        $unknown = array_diff(array_keys($data['documents'] ?? []), array_keys(PrintSettings::DOCUMENTS));
        if ($unknown) {
            throw ValidationException::withMessages(['documents' => 'Unknown document: '.implode(', ', $unknown).'.']);
        }

        $default = $this->set($data['default'] ?? [], 'default');
        $documents = [];
        foreach ($data['documents'] ?? [] as $key => $values) {
            if ($set = $this->set($values, 'documents.'.$key)) {
                $documents[$key] = $set;
            }
        }

        $paperBefore = $settings->paper($school->id);
        $styleBefore = $manager->style($school->id);
        $change = $settings->save($school, $default, $documents);
        $paper = ($data['paper'] ?? '') === '' ? null : $data['paper'];
        $settings->savePaper($school, $paper);
        $change['before']['paper'] = $paperBefore;
        $change['after']['paper'] = $paper;
        $change['before']['style'] = $styleBefore;
        $change['after']['style'] = $manager->saveStyle($school, $data['style'] ?? []);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $school->id, 'action' => 'print_settings_changed', 'metadata' => $change]);

        return redirect()->route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'printing'])->with('success', 'The print settings were saved.');
    }

    /** A page of numbered lines drawn 0 to 20 mm from each paper edge: printed with no margins, the first line the printer shows on each side is its real minimum margin. */
    public function marginTest(Request $request, PrintSettings $settings)
    {
        $paper = $request->query('paper');
        $paper = isset(PrintSettings::PAPERS[$paper]) ? $paper : ($settings->paper($request->user()->school_id) ?? 'folio');
        $sizes = ['a4' => [210, 297], 'letter' => [215.9, 279.4], 'legal' => [215.9, 355.6], 'longbond' => [215.9, 330.2], 'folio' => [215.9, 330.2]];
        [$width, $height] = $sizes[$paper];
        if ($request->query('orientation') === 'landscape') {
            [$width, $height] = [$height, $width];
        }

        return view('print-margin-test', ['paperName' => PrintSettings::PAPERS[$paper], 'width' => $width, 'height' => $height, 'landscape' => $width > $height]);
    }

    /** The scenarios of the print test document: how many tables, how many rows, how long the texts are. */
    public const TEST_SCENARIOS = [
        'short' => ['Short document', [5], false, true],
        'long' => ['Long table (60 rows)', [60], false, true],
        'many' => ['Many rows (150 rows)', [150], false, true],
        'lengthy' => ['Lengthy descriptions', [30], false, true],
        'huge' => ['Exceptionally long rows', [8], true, true],
        'multi' => ['Three tables', [12, 18, 10], false, true],
        'pages' => ['Three or more pages with signatures', [90], false, true],
    ];

    /** A document with the tables and texts of the chosen scenario, printed by the shared engine: the page to check the margins, the page breaks and the table continuation with. */
    public function testDocument(Request $request, PrintSettings $settings)
    {
        $key = $request->query('scenario');
        $key = isset(self::TEST_SCENARIOS[$key]) ? $key : 'long';
        [$label, $counts, $split, $signatures] = self::TEST_SCENARIOS[$key];
        $paper = $request->query('paper');
        $paper = isset(PrintSettings::PAPERS[$paper]) ? $paper : ($settings->paper($request->user()->school_id) ?? 'folio');

        $words = explode(' ', 'Supplies and materials for the school year including paper ink folders envelopes markers staplers scissors tape notebooks and other office items delivered to the property custodian after inspection and acceptance');
        $text = function (int $row) use ($key, $words): string {
            $length = match ($key) {
                'lengthy' => 12 + ($row * 7) % 40,
                'huge' => 220,
                default => 4 + ($row % 5),
            };

            return ucfirst(implode(' ', array_map(fn ($i) => $words[($row + $i * 3) % count($words)], range(0, $length - 1))));
        };
        $rows = fn (int $count, int $offset) => array_map(fn ($i) => ['no' => $i + 1, 'text' => $text($i + $offset), 'unit' => ['pcs', 'box', 'ream', 'set'][$i % 4], 'qty' => 1 + $i % 9, 'cost' => 10 + ($i * 7) % 90], range(0, $count - 1));
        $titles = ['Office supplies', 'Printing materials', 'Other items'];
        $tables = [];
        foreach ($counts as $number => $count) {
            $tables[] = ['title' => $titles[$number] ?? 'Items', 'rows' => $rows($count, $number * 5), 'split' => $split];
        }

        return view('print-test-document', [
            'scenarioLabel' => $label, 'rowCount' => array_sum($counts), 'tables' => $tables, 'signatures' => $signatures, 'paper' => $paper,
            'orientation' => $request->query('orientation') === 'landscape' ? 'landscape' : 'portrait',
        ]);
    }

    /** Four margins in mm, or null when every box is blank (the default applies); a half-filled set is refused. */
    private function set(array $values, string $field): ?array
    {
        $filled = collect(self::SIDES)->filter(fn ($side) => ($values[$side] ?? '') !== '' && ($values[$side] ?? null) !== null);
        if ($filled->isEmpty()) {
            return null;
        }
        if ($filled->count() < 4) {
            throw ValidationException::withMessages([$field => 'Fill all four margins (top, right, bottom, left) or leave all four blank.']);
        }

        return collect(self::SIDES)->mapWithKeys(fn ($side) => [$side => round((float) $values[$side], 1)])->all();
    }
}
