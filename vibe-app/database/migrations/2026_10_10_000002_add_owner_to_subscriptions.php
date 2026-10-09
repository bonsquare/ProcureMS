<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
        });

        // Until now a subscription belonged to the school; give each one to the person who manages it.
        DB::table('subscriptions')->whereNull('user_id')->orderBy('id')->each(function ($subscription) {
            $owner = DB::table('users')->where('organization_id', $subscription->organization_id)->where('role', '!=', 'master_user')
                ->orderByRaw("role = 'school_admin' desc")->orderBy('id')->value('id');
            if ($owner) {
                DB::table('subscriptions')->where('id', $subscription->id)->update(['user_id' => $owner]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
