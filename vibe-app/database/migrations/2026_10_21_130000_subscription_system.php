<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // What the master charges this school for the Annual plan (below the list price), until the end date when there is one.
            $table->decimal('charge_amount', 10, 2)->nullable()->after('amount');
            $table->date('charge_ends_at')->nullable()->after('charge_amount');
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10);
            $table->decimal('value', 10, 2);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100);
            $table->date('paid_at');
            $table->decimal('list_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_to_pay', 10, 2);
            $table->string('drive_file_id');
            $table->text('drive_link');
            $table->string('file_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('list_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('paid_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('method', 30)->default('bank_transfer');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proof_id')->nullable()->constrained('payment_proofs')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('promo_codes');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['charge_amount', 'charge_ends_at']);
        });
    }
};
