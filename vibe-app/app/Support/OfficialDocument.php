<?php

namespace App\Support;

use App\Models\AgencySetting;
use App\Models\School;
use Illuminate\Support\Facades\Storage;

/**
 * Shared data helpers for the official printed documents, so every form pulls the header
 * (logos, names) from the school/organization profile instead of carrying its own copy.
 */
class OfficialDocument
{
    /**
     * Public URLs for the two header logos, or null where the profile has none.
     * Left: the department logo from Agency Settings (falls back to the national DepEd seal).
     * Right: the school's own uploaded logo, then the division logo. There is deliberately no
     * school-specific fallback: a school without a logo prints a blank logo area.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function logos(?School $school, ?AgencySetting $agency): array
    {
        $left = self::publicUrl($agency?->department_logo_path)
            ?? (is_file(public_path('images/official-deped-logo.png')) ? asset('images/official-deped-logo.png') : null);
        $right = self::publicUrl($school?->logo_path) ?? self::publicUrl($agency?->division_logo_path);

        return [$left, $right];
    }

    private static function publicUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
