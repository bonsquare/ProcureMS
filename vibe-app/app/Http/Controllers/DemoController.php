<?php

namespace App\Http\Controllers;

use App\Services\DemoResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The "Reset demo data" button. It exists only on the demo copy and only for the master user. */
class DemoController extends Controller
{
    public function reset(Request $request, DemoResetService $demo): RedirectResponse
    {
        abort_unless(config('app.demo'), 404);
        abort_unless($request->user()?->isMaster(), 403);

        // Built first: running the artisan commands inside the request resets the app's idea of its own address.
        $login = route('login');
        $demo->reset();

        // Every account was recreated, so the old session is of no use.
        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->to($login)->with('status', 'Demo data was reset. Sign in again.');
    }
}
