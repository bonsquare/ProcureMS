<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->date('starts_at')->nullable();
            $table->date('renews_at')->nullable();
            $table->date('canceled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
