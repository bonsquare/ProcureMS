<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The master user turns the screen of the whole system off and on. */
class ScreenController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isMaster(), 403);
        $data = $request->validate(['screen' => ['required', 'in:on,off']]);

        SystemSetting::put(SystemSetting::SCREEN_OFF, $data['screen'] === 'off' ? '1' : '0');
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $data['screen'] === 'off' ? 'screen_turned_off' : 'screen_turned_on', 'auditable_type' => SystemSetting::class, 'auditable_id' => 0, 'metadata' => ['screen' => $data['screen']]]);

        return redirect()->route('home', ['view' => 'screen'])->with('success', $data['screen'] === 'off'
            ? 'The screen is off. Everyone but you sees "Link is not available".'
            : 'The screen is on. Everyone can use the system again.');
    }
}
