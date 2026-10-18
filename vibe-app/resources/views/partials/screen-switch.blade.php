{{-- The master user's switch for the screen of the whole system (SystemSetting screen_off). --}}
<section class="mx-auto max-w-2xl overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">power_settings_new</span></span>
        <div><h2 class="text-sm font-bold text-primary">Screen</h2><p class="text-xs text-on-surface-variant">Turn the system off for everyone but you.</p></div>
    </div>
    <div class="space-y-4 p-5">
        <div class="flex items-center gap-3 rounded-lg border px-4 py-3 {{ $screenOff ? 'border-error/40 bg-error/5 text-error' : 'border-secondary/40 bg-secondary/5 text-secondary' }}">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $screenOff ? 'block' : 'check_circle' }}</span>
            <p class="text-sm font-semibold">{{ $screenOff ? 'The screen is OFF. Visitors and users see "This System is Under Maintenance".' : 'The screen is ON. The system is open to its users.' }}</p>
        </div>
        <p class="text-xs leading-5 text-on-surface-variant">While the screen is off, anyone who opens the link sees only the "This System is Under Maintenance" page. Only the master user can still sign in (sign in at <span class="font-semibold">/login?master</span>; the plain /login shows the same page) to turn the screen back on.</p>
        <form method="POST" action="{{ route('screen.update') }}" @if(! $screenOff) onsubmit="return confirm('Turn the screen off? Everyone but you will see System Under Maintenance.')" @endif>@csrf @method('PUT')
            <input type="hidden" name="screen" value="{{ $screenOff ? 'on' : 'off' }}">
            <button class="rounded-lg px-5 py-2.5 text-sm font-bold text-white shadow-sm {{ $screenOff ? 'bg-secondary hover:opacity-90' : 'bg-error hover:opacity-90' }}">{{ $screenOff ? 'Turn the screen on' : 'Turn the screen off' }}</button>
        </form>
    </div>
</section>

@php $offTabs = \App\Models\SystemSetting::sectionsOff(); @endphp
<section class="mx-auto mt-5 max-w-2xl overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">tab</span></span>
        <div><h2 class="text-sm font-bold text-primary">Tabs</h2><p class="text-xs text-on-surface-variant">Put single tabs under maintenance while the rest of the system keeps working.</p></div>
    </div>
    <form method="POST" action="{{ route('screen.tabs') }}" class="space-y-4 p-5">@csrf @method('PUT')
        <div class="flex items-center gap-3 rounded-lg border px-4 py-3 {{ $offTabs ? 'border-error/40 bg-error/5 text-error' : 'border-secondary/40 bg-secondary/5 text-secondary' }}">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $offTabs ? 'construction' : 'check_circle' }}</span>
            <p class="text-sm font-semibold">{{ $offTabs ? count($offTabs).' tab'.(count($offTabs) === 1 ? ' is' : 's are').' under maintenance.' : 'Every tab is on.' }}</p>
        </div>
        <p class="text-xs leading-5 text-on-surface-variant">Tick a tab to switch it off. Everyone but you sees "This Site is Under Maintenance" with a Back to Dashboard button when opening it; the Dashboard, sign-in and your own access stay open. Untick it and save to turn it on again.</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach(\App\Support\ScreenSections::ALL as $tabKey => [$tabLabel])
                <label class="flex items-center gap-3 rounded-lg border border-outline-variant/50 px-3 py-2.5 text-sm font-medium {{ in_array($tabKey, $offTabs, true) ? 'bg-error/5' : '' }}"><input type="checkbox" name="sections[]" value="{{ $tabKey }}" @checked(in_array($tabKey, $offTabs, true)) class="h-4 w-4 rounded border-outline-variant text-error focus:ring-error"><span class="flex-1">{{ $tabLabel }}</span>@if(in_array($tabKey, $offTabs, true))<span class="rounded-full bg-error/10 px-2 py-0.5 text-[10px] font-bold uppercase text-error">Off</span>@endif</label>
            @endforeach
        </div>
        <div><button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-container">Save tabs</button></div>
    </form>
</section>
