<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The Lubas Elementary School seal used to be a hard-coded fallback on every school's documents.
 * Documents now read the logo from each school's profile only, so keep Lubas's seal on Lubas's own profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        $source = public_path('images/official-school-logo.png');
        $school = DB::table('schools')->where('name', 'Lubas Elementary School')->whereNull('logo_path')->first();
        if (! $school || ! is_file($source)) {
            return;
        }

        $target = 'logos/lubas-elementary-school.png';
        Storage::disk('public')->put($target, file_get_contents($source));
        DB::table('schools')->where('id', $school->id)->update(['logo_path' => $target]);
    }

    public function down(): void
    {
        // The copied logo stays on the school profile; nothing to undo safely.
    }
};
