<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('procurement_role')->nullable();
            $table->string('bac_role')->nullable();
            $table->timestamps();
        });

        DB::table('users')
            ->where('role', 'school_staff')
            ->whereNotNull('school_id')
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('school_staff')->insert([
                    'school_id' => $user->school_id,
                    'name' => $user->name,
                    'position' => $user->position,
                    'procurement_role' => $user->procurement_role,
                    'bac_role' => $user->bac_role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('users')->where('role', 'school_staff')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('school_staff');
    }
};
