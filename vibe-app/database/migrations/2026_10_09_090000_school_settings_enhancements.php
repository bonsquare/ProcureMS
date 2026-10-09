<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('user_code', 20)->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
        });

        Schema::table('school_staff', function (Blueprint $table) {
            $table->string('employee_no', 20)->nullable()->unique();
        });

        Schema::table('agency_settings', function (Blueprint $table) {
            $table->string('district_address', 1000)->nullable();
            $table->string('district_head')->nullable();
            $table->string('district_logo_path')->nullable();
        });

        Schema::create('staff_role_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role_group', 30);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['organization_id', 'role_group', 'name']);
        });

        // Existing accounts and employees get their generated ids and a username taken from their email.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr((string) $user->email, '@', true) ?: 'user'.$user->id)) ?: 'user'.$user->id;
            $username = in_array($base, $taken, true) ? $base.$user->id : $base;
            $taken[] = $username;
            DB::table('users')->where('id', $user->id)->update(['user_code' => 'USR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT), 'username' => $username]);
        }
        foreach (DB::table('school_staff')->orderBy('id')->pluck('id') as $id) {
            DB::table('school_staff')->where('id', $id)->update(['employee_no' => 'EMP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_role_options');

        Schema::table('agency_settings', function (Blueprint $table) {
            $table->dropColumn(['district_address', 'district_head', 'district_logo_path']);
        });

        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropUnique(['employee_no']);
            $table->dropColumn('employee_no');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['user_code']);
            $table->dropColumn(['username', 'user_code', 'phone', 'status', 'last_login_at', 'password_changed_at']);
        });
    }
};
