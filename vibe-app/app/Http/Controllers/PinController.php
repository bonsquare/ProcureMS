<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PinController extends Controller
{
    /** Sets, changes or resets the signed-in user's own 6-digit PIN; the login password is the proof. */
    public function store(Request $request)
    {
        $key = 'pin-reset:'.$request->user()->id;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many tries. Wait a few minutes.');

        $data = $request->validateWithBag('pin', [
            'pin' => ['required', 'digits:6', 'confirmed'],
            'password' => ['required', 'current_password'],
        ], [
            'pin.digits' => 'The PIN is exactly 6 digits.',
            'pin.confirmed' => 'The two PINs do not match.',
            'password.current_password' => 'The password is wrong.',
        ]);
        RateLimiter::hit($key, 300);

        $request->user()->forceFill(['pin' => $data['pin']])->save();
        RateLimiter::clear($key);

        return $request->expectsJson()
            ? response()->json(['message' => 'Your PIN was saved.'])
            : back()->with('success', 'Your PIN was saved.');
    }
}
