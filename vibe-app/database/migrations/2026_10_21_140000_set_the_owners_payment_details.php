<?php

use App\Support\PaymentSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The owner's own account and QR (GoTyme) become the payment details of a running system; a field that is already filled is never replaced, and a new install (no user yet) starts empty. */
    public function up(): void
    {
        if (! DB::table('users')->exists()) {
            return;
        }
        $qr = database_path('data/payment-qr.png');
        $defaults = [
            'bank_name' => 'GoTyme Bank',
            'account_name' => 'Bonifacio Jr Orong',
            'account_number' => '014062241863',
            'qr' => is_file($qr) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($qr)) : null,
        ];
        $current = PaymentSettings::get();
        PaymentSettings::put(array_filter($defaults, fn ($value, $key) => $value && blank($current[$key]), ARRAY_FILTER_USE_BOTH));
    }

    public function down(): void
    {
        // The payment details stay as the master left them.
    }
};
