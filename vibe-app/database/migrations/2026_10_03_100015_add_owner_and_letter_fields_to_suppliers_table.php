<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('owner_given_name')->nullable()->after('business_name');
            $table->string('owner_middle_initial', 10)->nullable()->after('owner_given_name');
            $table->string('owner_last_name')->nullable()->after('owner_middle_initial');
            $table->string('letter_addressed_to')->nullable()->after('owner_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['owner_given_name', 'owner_middle_initial', 'owner_last_name', 'letter_addressed_to']);
        });
    }
};
