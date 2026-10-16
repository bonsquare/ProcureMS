<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Cloudflare Turnstile: the human check on the sign-in and registration forms. It is off until both keys are set. */
class Turnstile
{
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Checks the answer of the widget with Cloudflare.
     *
     * @throws ValidationException
     */
    public static function verify(Request $request, string $field = 'email'): void
    {
        if (! self::enabled()) {
            return;
        }

        $token = (string) $request->input('cf-turnstile-response');
        $passed = false;
        if ($token !== '') {
            try {
                $passed = (bool) Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ])->json('success');
            } catch (\Throwable) {
                $passed = false;
            }
        }

        if (! $passed) {
            throw ValidationException::withMessages([$field => 'Please complete the security check and try again.']);
        }
    }
}
