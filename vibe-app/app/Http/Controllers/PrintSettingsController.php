<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Services\PrintSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Saves the page margins of a school (the Printing tab of School Settings). */
class PrintSettingsController extends Controller
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    public function update(Request $request, PrintSettings $settings): RedirectResponse
    {
        $margin = ['nullable', 'numeric', 'between:0,40'];
        $rules = ['school_id' => ['required', 'integer'], 'default' => ['nullable', 'array'], 'documents' => ['nullable', 'array']];
        foreach (self::SIDES as $side) {
            $rules['default.'.$side] = $margin;
            $rules['documents.*.'.$side] = $margin;
        }
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

        $change = $settings->save($school, $default, $documents);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $school->id, 'action' => 'print_settings_changed', 'metadata' => $change]);

        return redirect()->route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => 'printing'])->with('status', 'The print settings were saved.');
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
