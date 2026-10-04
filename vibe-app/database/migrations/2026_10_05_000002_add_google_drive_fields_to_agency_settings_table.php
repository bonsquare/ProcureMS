<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->boolean('google_drive_enabled')->default(false)->after('division_logo_path');
            $table->string('google_drive_folder_name')->nullable()->after('google_drive_enabled');
            $table->string('google_drive_folder_id')->nullable()->after('google_drive_folder_name');
            $table->text('google_drive_folder_url')->nullable()->after('google_drive_folder_id');
            $table->timestamp('google_drive_connected_at')->nullable()->after('google_drive_folder_url');
        });
    }

    public function down(): void
    {
        Schema::table('agency_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_drive_enabled',
                'google_drive_folder_name',
                'google_drive_folder_id',
                'google_drive_folder_url',
                'google_drive_connected_at',
            ]);
        });
    }
};
