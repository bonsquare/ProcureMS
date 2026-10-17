{{-- The master user's switch for the screen of the whole system (SystemSetting screen_off). --}}
<section class="mx-auto max-w-2xl overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">power_settings_new</span></span>
        <div><h2 class="text-sm font-bold text-primary">Screen</h2><p class="text-xs text-on-surface-variant">Turn the system off for everyone but you.</p></div>
    </div>
    <div class="space-y-4 p-5">
        <div class="flex items-center gap-3 rounded-lg border px-4 py-3 {{ $screenOff ? 'border-error/40 bg-error/5 text-error' : 'border-secondary/40 bg-secondary/5 text-secondary' }}">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $screenOff ? 'block' : 'check_circle' }}</span>
            <p class="text-sm font-semibold">{{ $screenOff ? 'The screen is OFF. Visitors and users see "Link is not available".' : 'The screen is ON. The system is open to its users.' }}</p>
        </div>
        <p class="text-xs leading-5 text-on-surface-variant">While the screen is off, anyone who opens the link sees only "Link is not available". Only the master user can still sign in (sign in at <span class="font-semibold">/login?master</span>; the plain /login says "Link is not available" too) to turn the screen back on.</p>
        <form method="POST" action="{{ route('screen.update') }}" @if(! $screenOff) onsubmit="return confirm('Turn the screen off? Everyone but you will see Link is not available.')" @endif>@csrf @method('PUT')
            <input type="hidden" name="screen" value="{{ $screenOff ? 'on' : 'off' }}">
            <button class="rounded-lg px-5 py-2.5 text-sm font-bold text-white shadow-sm {{ $screenOff ? 'bg-secondary hover:opacity-90' : 'bg-error hover:opacity-90' }}">{{ $screenOff ? 'Turn the screen on' : 'Turn the screen off' }}</button>
        </form>
    </div>
</section>
