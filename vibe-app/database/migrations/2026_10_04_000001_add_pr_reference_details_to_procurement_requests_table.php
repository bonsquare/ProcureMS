<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->string('entity_name')->nullable()->after('title');
            $table->string('department_name')->nullable()->after('entity_name');
            $table->string('section')->nullable()->after('department_name');
            $table->string('sai_number')->nullable()->after('section');
            $table->date('sai_date')->nullable()->after('sai_number');
            $table->string('responsibility_center_code')->nullable()->after('sai_date');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn(['entity_name', 'department_name', 'section', 'sai_number', 'sai_date', 'responsibility_center_code']);
        });
    }
};
