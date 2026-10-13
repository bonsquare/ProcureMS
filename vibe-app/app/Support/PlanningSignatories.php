<?php

namespace App\Support;

use App\Models\Aip;
use App\Models\AppPlan;
use App\Models\SipPlan;
use App\Models\SobPlan;

/**
 * The signatories last saved on the school's planning documents (SIP, AIP, SOB and APP), used to fill in the signature fields of a document
 * that has none saved yet, so the same people are not typed again on every document. Only a suggestion: nothing is saved until the user saves.
 */
class PlanningSignatories
{
    /** The documents that have each signature, newest first is decided by when they were last saved. */
    private const SOURCES = [
        'prepared_by' => [SipPlan::class, Aip::class, SobPlan::class, AppPlan::class],
        'recommended_by' => [SipPlan::class, SobPlan::class, AppPlan::class],
        'noted_by' => [Aip::class],
        'approved_by' => [SipPlan::class, Aip::class, SobPlan::class, AppPlan::class],
    ];

    /**
     * The name and position last saved for each signature of the school's planning documents.
     *
     * @param  array<int, string>  $slots  the signatures wanted, for example ['prepared_by', 'recommended_by', 'approved_by']
     * @return array<string, array{name: string, position: ?string}> one entry per slot that has a saved name
     */
    public static function lastUsed(int $schoolId, array $slots): array
    {
        $found = [];
        foreach ($slots as $slot) {
            $latest = null;
            foreach (self::SOURCES[$slot] ?? [] as $model) {
                $record = $model::query()->where('school_id', $schoolId)
                    ->whereNotNull($slot.'_name')->where($slot.'_name', '!=', '')
                    ->latest('updated_at')->first([$slot.'_name', $slot.'_position', 'updated_at']);
                if ($record && (! $latest || $record->updated_at > $latest->updated_at)) {
                    $latest = $record;
                }
            }
            if ($latest) {
                $found[$slot] = ['name' => (string) $latest->{$slot.'_name'}, 'position' => $latest->{$slot.'_position'}];
            }
        }

        return $found;
    }

    /**
     * The signature slots of a document as the shared signatory editor shows them: the saved values first, then what the school last used,
     * then the default of the form. A name that comes from a suggestion is marked so the editor can say it is not saved yet.
     *
     * @param  array<int, array{0: string, 1: string, 2: string, 3?: ?string, 4?: ?string, 5?: ?string, 6?: ?string}>  $definitions  key, legend, default title, default name, default position, name placeholder, position hint
     * @param  array<string, array{name: string, position: ?string}>  $lastUsed
     * @return array<int, array<string, mixed>>
     */
    public static function slots(array $definitions, ?object $model, array $lastUsed = [], bool $useOld = false): array
    {
        $slots = [];
        foreach ($definitions as [$key, $legend, $defaultRole, $defaultName, $defaultPosition, $namePlaceholder, $positionHint]) {
            $savedName = (string) ($model?->{$key.'_name'} ?? '');
            $suggest = $savedName === '' ? ($lastUsed[$key] ?? null) : null;
            $name = $savedName !== '' ? $savedName : ($suggest['name'] ?? $defaultName ?? '');
            $position = $savedName !== '' ? ($model?->{$key.'_position'} ?? $defaultPosition) : ($suggest ? ($suggest['position'] ?: $defaultPosition) : ($model?->{$key.'_position'} ?? $defaultPosition));
            $role = ($model?->{$key.'_role'} ?? null) ?: $defaultRole;
            if ($useOld) {
                $name = old($key.'_name', $name);
                $position = old($key.'_position', $position);
                $role = old($key.'_role', $role);
            }
            $slots[] = [
                'key' => $key, 'legend' => $legend, 'role' => (string) $role, 'name' => (string) $name, 'position' => (string) $position,
                'status' => $savedName !== '' ? 'saved' : ($name !== '' ? 'suggested' : 'empty'),
                'namePlaceholder' => $namePlaceholder, 'positionHint' => $positionHint,
            ];
        }

        return $slots;
    }
}
