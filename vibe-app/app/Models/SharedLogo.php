<?php

namespace App\Models;

use App\Support\PlaceNames;
use Illuminate\Database\Eloquent\Model;

class SharedLogo extends Model
{
    public const DEPARTMENT_KEY = 'deped';

    protected $fillable = ['kind', 'key', 'path', 'uploaded_by'];

    /**
     * The lookup key of a division: region and division name, lower-cased with spaces collapsed.
     * A blank division has no key, so it never matches a shared logo.
     */
    public static function divisionKey(?string $region, ?string $division): ?string
    {
        $normalize = fn (?string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
        $division = $normalize($division);
        $region = PlaceNames::canonicalRegion($region);
        if ($division === '') {
            return null;
        }

        return $normalize($region).'|'.$division;
    }

    public static function department(): ?self
    {
        return self::where('kind', 'department')->where('key', self::DEPARTMENT_KEY)->first();
    }

    /** The division of a school: its own profile first, then the agency record (same order the printed forms use). */
    public static function keyForSchool(?School $school, ?AgencySetting $agency = null): ?string
    {
        return self::divisionKey(
            $school?->region ?: $agency?->region_name,
            $school?->division ?: ($agency?->division_name ?: $agency?->division_office),
        );
    }

    public static function forDivision(?School $school, ?AgencySetting $agency = null): ?self
    {
        $key = self::keyForSchool($school, $agency);

        return $key ? self::where('kind', 'division')->where('key', $key)->first() : null;
    }
}
