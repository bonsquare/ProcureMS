<?php

use App\Support\SharedLogoBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_logos', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('key');
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'key']);
        });

        SharedLogoBackfill::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_logos');
    }
};
