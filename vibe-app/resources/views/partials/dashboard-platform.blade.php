@php
    // The super admin's own figures: the schools, their subscriptions, the income and the proofs waiting. [label, value, note, icon, colour, background, border, link]
    $peso = fn (float $value) => '₱'.number_format($value, 2);
    $st = $platform['status'];
    $tiles = [
        ['Total Schools', $platform['schools'], $platform['active_schools'].' active · '.$platform['waiting'].' waiting for activation', 'apartment', '#0f7482', '#e1f4f5', '#b7e0e4', route('school-settings')],
        ['Subscriptions', ($st['active'] ?? 0), ($st['trial'] ?? 0).' on trial · '.($st['grace_period'] ?? 0).' in grace · '.($st['free'] ?? 0).' free', 'verified', '#156a52', '#e4f6ee', '#bfe8d6', route('subscriptions')],
        ['Proofs to review', $platform['proofs'], $platform['proofs'] ? 'Payments waiting for your decision' : 'Nothing waiting', 'receipt_long', '#b8455a', '#fdebee', '#f6c8d0', route('subscriptions')],
        ['Ending within 30 days', $platform['ending_soon'], $platform['locked'].' expired or suspended', 'event_upcoming', '#1b6fa8', '#e6f3fc', '#c3e1f5', route('subscriptions')],
        ['Income '.$platform['year'], $peso($platform['income_year']), $peso($platform['income_month']).' this month', 'payments', '#6a55b8', '#eeeafb', '#d6cef3', route('subscriptions')],
        ['Users', $platform['users'], 'Across all schools', 'groups', '#0f7482', '#e1f4f5', '#b7e0e4', route('user-management')],
    ];
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($tiles as [$tileLabel, $tileValue, $tileNote, $tileIcon, $tileColor, $tileBg, $tileEdge, $tileHref])
        <a href="{{ $tileHref }}" class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 pl-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c]" style="background:linear-gradient(135deg,{{ $tileBg }},#fff 135%);border-color:{{ $tileEdge }}">
            <span class="absolute inset-y-0 left-0 w-1.5" style="background:{{ $tileColor }}" aria-hidden="true"></span>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wide" style="color:{{ $tileColor }}">{{ $tileLabel }}</p><p @if(is_int($tileValue))data-countup @endif class="mt-1.5 text-3xl font-extrabold leading-none tracking-tight" style="color:{{ $tileColor }}">{{ $tileValue }}</p></div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background:{{ $tileColor }}1f;color:{{ $tileColor }}"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $tileIcon }}</span></span>
            </div>
            <p class="mt-3 truncate text-xs text-on-surface-variant">{{ $tileNote }}</p>
        </a>
    @endforeach
</div>

