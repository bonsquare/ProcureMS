@php
    $counts = $counts ?? [];
    $cards = [['unassigned', 'Unassigned', 'border-amber-300 bg-amber-50 text-amber-900'], ['mine', 'Mine', 'border-primary/30 bg-primary/5 text-primary'], ['pending', 'Pending', 'border-amber-200 bg-amber-50/60 text-amber-900'], ['for_process', 'On process', 'border-secondary/30 bg-secondary/5 text-secondary'], ['on_hold', 'On hold', 'border-slate-300 bg-slate-100 text-slate-700'], ['approved', 'Approved', 'border-emerald-300 bg-emerald-50 text-emerald-800']];
@endphp
<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0 flex-1">
        <h2 class="text-lg font-semibold">Support desk</h2>
        <p class="mt-1 break-words text-xs text-on-surface-variant">Pick a request from the queue, or take the one that has waited longest. What you do here is posted in the Customer Services Telegram chat, and what staff do there shows here.</p>
    </div>
    <form method="POST" action="{{ route('support.take-next') }}" class="shrink-0">@csrf
        <button @disabled(($counts['unassigned'] ?? 0) === 0) class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">how_to_reg</span>Take next ({{ $counts['unassigned'] ?? 0 }} waiting)</button>
    </form>
</div>
<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
    @foreach($cards as [$key, $label, $tone])
        <a href="{{ route('support', $key === 'open' ? [] : ['filter' => $key]) }}" class="min-w-0 rounded-xl border p-3 hover:shadow-sm {{ $tone }}"><span class="block text-2xl font-bold leading-none">{{ $counts[$key] ?? 0 }}</span><span class="mt-1 block break-words text-[11px] font-bold uppercase tracking-wide">{{ $label }}</span></a>
    @endforeach
</div>
<h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-on-surface-variant">Waiting the longest, with nobody</h3>
@if(($oldest ?? collect())->isEmpty())
    <p class="mt-2 text-sm text-on-surface-variant">Nothing is waiting: every open request has someone.</p>
@else
    <ul class="mt-2 divide-y divide-outline-variant/30 rounded-xl border border-outline-variant/40">
        @foreach($oldest as $item)
            <li><a href="{{ route('support.show', $item) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 hover:bg-surface-low">
                <span class="min-w-0 flex-1 break-words text-sm font-semibold">{{ $item->subject ?: 'Support request' }}<span class="block break-words text-[11px] font-normal text-on-surface-variant">{{ $item->user?->name }}@if($item->school) · {{ $item->school->name }}@endif</span></span>
                <span class="shrink-0 text-[11px] font-semibold text-amber-800">Open {{ $item->created_at->diffForHumans(null, true, true) }}</span>
            </a></li>
        @endforeach
    </ul>
@endif
