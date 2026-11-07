<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** The signed-in user's own 6-digit PIN, asked instead of the password when an edit is confirmed. */
class CurrentPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();
        if (! $user || ! $user->pin) {
            $fail('You have no PIN yet. Set your PIN first.');

            return;
        }

        $key = 'pin-check:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $fail('Too many wrong PINs. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s), or reset your PIN with your password.');

            return;
        }

        if (! is_string($value) || ! Hash::check($value, $user->pin)) {
            RateLimiter::hit($key, 300);
            $fail('The PIN is wrong.');

            return;
        }

        RateLimiter::clear($key);
    }
}
