<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps region, division and district names spelled one way across the whole system: the names already saved
 * are offered as suggestions, and a typed name that differs only in case or spacing (or, for a region, in
 * "7" against "VII") is saved with the spelling the system already has.
 */
class PlaceNames
{
    /** @var array<string, list<array{0: string, 1: string}>> */
    private const COLUMNS = [
        'region' => [['schools', 'region'], ['agency_settings', 'region_name']],
        'division' => [['schools', 'division'], ['agency_settings', 'division_office'], ['agency_settings', 'division_name']],
        'district' => [['schools', 'district'], ['agency_settings', 'district_name']],
    ];

    private const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV', 16 => 'XVI', 17 => 'XVII', 18 => 'XVIII'];

    /** "Region 7", "region 07", "7" and "vii" all become "Region VII"; "4a" becomes "Region IV-A"; other names are only tidied. */
    public static function canonicalRegion(?string $value): ?string
    {
        $value = self::collapse($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(?:region\s*)?(\d{1,2}|[ivx]{1,5})\s*-?\s*([ab])?$/i', $value, $match)) {
            $number = ctype_digit($match[1]) ? (int) $match[1] : (array_search(strtoupper($match[1]), self::ROMAN, true) ?: 0);
            if (isset(self::ROMAN[$number])) {
                return 'Region '.self::ROMAN[$number].(($match[2] ?? '') !== '' ? '-'.strtoupper($match[2]) : '');
            }
        }

        return $value;
    }

    /**
     * Every distinct name already in the system for 'region', 'division' or 'district', one spelling each (the most
     * used one), sorted. Reads names only, never school or user details, so the public registration page can use it.
     *
     * @return Collection<int, string>
     */
    public static function suggestions(string $field): Collection
    {
        $spellings = [];
        foreach (self::COLUMNS[$field] ?? [] as [$table, $column]) {
            foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column) as $stored) {
                $name = $field === 'region' ? self::canonicalRegion($stored) : self::collapse($stored);
                if ($name !== null && $name !== '') {
                    $spellings[mb_strtolower($name)][$name] = ($spellings[mb_strtolower($name)][$name] ?? 0) + 1;
                }
            }
        }

        return collect($spellings)
            ->map(function (array $counts): string {
                ksort($counts);
                arsort($counts);

                return (string) array_key_first($counts);
            })
            ->values()
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values();
    }

    /** The name to save: tidied, a region written in numbers made Roman, and the system's own spelling when it already has the name. */
    public static function snap(string $field, ?string $value): ?string
    {
        $name = $field === 'region' ? self::canonicalRegion($value) : (self::collapse($value) ?: null);
        if ($name === null) {
            return null;
        }
        $key = mb_strtolower($name);

        return self::suggestions($field)->first(fn (string $known) => mb_strtolower($known) === $key) ?? $name;
    }

    /**
     * Runs snap() over the given keys of a validated request.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $fields  data key => 'region' | 'division' | 'district'
     * @return array<string, mixed>
     */
    public static function snapFields(array $data, array $fields): array
    {
        foreach ($fields as $key => $field) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::snap($field, $data[$key]);
            }
        }

        return $data;
    }

    private static function collapse(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
