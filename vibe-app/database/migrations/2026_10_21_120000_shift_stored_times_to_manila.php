<?php

use App\Support\ManilaTimeShift;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ManilaTimeShift::run();
    }

    public function down(): void
    {
        // The stored times stay on Philippine time.
    }
};
