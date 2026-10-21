<?php

namespace App\Support;

use App\Models\SystemSetting;

/** Where schools send their subscription payment: the master's bank account, the QR code of it and the Google email the proofs of payment are shared with. */
class PaymentSettings
{
    private const KEYS = [
        'bank_name' => 'pay_bank_name',
        'account_name' => 'pay_account_name',
        'account_number' => 'pay_account_number',
        'qr' => 'pay_qr',
        'master_email' => 'pay_master_email',
    ];

    /** @return array{bank_name: ?string, account_name: ?string, account_number: ?string, qr: ?string, master_email: ?string} */
    public static function get(): array
    {
        return array_map(fn (string $key) => SystemSetting::get($key) ?: null, self::KEYS);
    }

    /** Saves the given values (a key that is left out stays as it is). @param  array<string, ?string>  $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::KEYS) as $name => $value) {
            SystemSetting::put(self::KEYS[$name], $value === '' ? null : $value);
        }
    }

    /** True when the school has what it needs to pay: the account and its name. */
    public static function ready(): bool
    {
        $settings = self::get();

        return filled($settings['account_name']) && filled($settings['account_number']);
    }
}
