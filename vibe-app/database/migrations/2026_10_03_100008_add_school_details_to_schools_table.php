<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('region')->nullable()->after('address');
            $table->string('division')->nullable()->after('region');
            $table->string('district')->nullable()->after('division');
            $table->string('school_type')->nullable()->after('district');
            $table->string('school_head')->nullable()->after('school_type');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['region', 'division', 'district', 'school_type', 'school_head']);
        });
    }
};
