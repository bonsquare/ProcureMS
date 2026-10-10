<?php

namespace App\Support;

use App\Models\AgencySetting;
use App\Models\School;
use App\Models\SharedLogo;

class SharedLogoBackfill
{
    /** Copies each organization's own department and division logo into the shared table; never overwrites a row. */
    public static function run(): void
    {
        foreach (AgencySetting::withoutGlobalScopes()->orderBy('id')->get() as $agency) {
            if ($agency->department_logo_path) {
                SharedLogo::firstOrCreate(
                    ['kind' => 'department', 'key' => SharedLogo::DEPARTMENT_KEY],
                    ['path' => $agency->department_logo_path],
                );
            }
            if (! $agency->division_logo_path) {
                continue;
            }
            $schools = School::withoutGlobalScopes()->where('organization_id', $agency->organization_id)->get();
            $keys = $schools->map(fn (School $school) => SharedLogo::keyForSchool($school, $agency))->filter()->unique();
            if ($keys->isEmpty()) {
                $keys = collect([SharedLogo::keyForSchool(null, $agency)])->filter();
            }
            foreach ($keys as $key) {
                SharedLogo::firstOrCreate(['kind' => 'division', 'key' => $key], ['path' => $agency->division_logo_path]);
            }
        }
    }
}
