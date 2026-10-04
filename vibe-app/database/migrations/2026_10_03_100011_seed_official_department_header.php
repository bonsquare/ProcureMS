<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agency_settings')->updateOrInsert(
            ['id' => 1],
            [
                'republic_name' => 'Republic of the Philippines',
                'department_name' => 'Department of Education',
                'region_name' => 'Region XII',
                'division_office' => 'Schools Division Office of Cotabato',
                'district_name' => 'Magpet East District',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('agency_settings')->where('id', 1)->delete();
    }
};
