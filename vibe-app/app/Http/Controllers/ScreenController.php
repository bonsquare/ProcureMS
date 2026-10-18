<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Support\ScreenSections;
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
            ? 'The screen is off. Everyone but you sees "This System is Under Maintenance".'
            : 'The screen is on. Everyone can use the system again.');
    }

    /** The master user switches single tabs off: users of the system see that tab as under maintenance while the rest of the system keeps working. */
    public function sections(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isMaster(), 403);
        $data = $request->validate(['sections' => ['nullable', 'array'], 'sections.*' => ['string', 'in:'.implode(',', ScreenSections::keys())]]);
        $before = SystemSetting::sectionsOff();
        $after = array_values(array_intersect(ScreenSections::keys(), $data['sections'] ?? []));

        SystemSetting::put(SystemSetting::SCREEN_OFF_SECTIONS, json_encode($after));
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'screen_tabs_changed', 'auditable_type' => SystemSetting::class, 'auditable_id' => 0, 'metadata' => ['before' => $before, 'after' => $after]]);

        return redirect()->route('home', ['view' => 'screen'])->with('success', $after === []
            ? 'Every tab is on.'
            : 'Under maintenance for everyone but you: '.collect($after)->map(fn ($key) => ScreenSections::ALL[$key][0])->implode(', ').'.');
    }
}
