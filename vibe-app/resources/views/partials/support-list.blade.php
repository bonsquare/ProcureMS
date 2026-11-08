@php
    $filter = $filter ?? 'open';
    $q = $q ?? '';
    $counts = $counts ?? null;
    $current = $current ?? null;
    $chipClass = fn (string $status) => ['for_process' => 'bg-secondary/10 text-secondary', 'approved' => 'bg-emerald-100 text-emerald-800', 'on_hold' => 'bg-slate-200 text-slate-700', 'resolved' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-amber-100 text-amber-800';
    // The master user's queue keeps its filter and search word while moving from one request to the next.
    $keep = $canEdit ? array_filter(['filter' => $filter !== 'open' ? $filter : null, 'q' => $q !== '' ? $q : null]) : [];
    $filterUrl = fn (string $key) => ($current ? route('support.show', ['supportMessage' => $current->id] + array_filter(['filter' => $key !== 'open' ? $key : null, 'q' => $q !== '' ? $q : null])) : route('support', array_filter(['filter' => $key !== 'open' ? $key : null, 'q' => $q !== '' ? $q : null])));
@endphp
@if($canEdit && $counts)
    <nav class="flex flex-wrap gap-1.5" aria-label="Queue filters">
        @foreach(\App\Http\Controllers\SupportController::FILTERS as $key => $label)
            @php $active = $filter === $key; $alert = $key === 'unassigned' && ($counts[$key] ?? 0) > 0; @endphp
            <a href="{{ $filterUrl($key) }}" @if($active) aria-current="true" @endif class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $active ? 'border-primary bg-primary text-white' : ($alert ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'border-outline-variant/50 bg-white text-on-surface hover:bg-surface-low') }}">{{ $label }}<span class="rounded-full px-1.5 text-[10px] font-bold {{ $active ? 'bg-white/25 text-white' : 'bg-surface-container text-on-surface-variant' }}">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('support.take-next') }}" class="mt-2">@csrf
        <button @disabled(($counts['unassigned'] ?? 0) === 0) class="flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-primary/40 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">how_to_reg</span>Take next waiting request ({{ $counts['unassigned'] ?? 0 }})</button>
    </form>
@endif
<div class="{{ $canEdit && $counts ? 'mt-3' : '' }}">
@if($messages->isEmpty())
    <p class="text-sm text-on-surface-variant">{{ $canEdit ? 'No requests in this view.' : 'No requests yet. Create one and Customer Services will answer here.' }}</p>
@else
    <ul class="space-y-2">
        @foreach($messages as $item)
            @php
                $stripe = ['for_process' => 'border-l-secondary', 'approved' => 'border-l-emerald-500', 'on_hold' => 'border-l-slate-400', 'resolved' => 'border-l-slate-300'][$item->status] ?? 'border-l-amber-400';
                $hours = $item->created_at->diffInHours(now());
                $age = $hours >= 72 ? 'font-bold text-error' : ($hours >= 24 ? 'font-bold text-amber-700' : 'text-on-surface-variant');
                $selected = $current && $current->id === $item->id;
            @endphp
            <li><a href="{{ route('support.show', ['supportMessage' => $item->id] + $keep) }}" @if($selected) aria-current="page" @endif class="block rounded-lg border border-l-4 p-2.5 hover:bg-surface-low {{ $stripe }} {{ $selected ? 'border-primary bg-primary/5' : 'border-outline-variant/40' }}">
                <span class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                    <span class="min-w-0 flex-1 break-words text-[13px] font-semibold leading-snug">{{ $item->subject ?: 'Support request' }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $chipClass($item->status) }}">{{ \App\Support\SupportStatus::label($item->status) }}</span>
                </span>
                @if($canEdit)<span class="mt-0.5 block break-words text-[11px] text-on-surface-variant">{{ $item->user?->name }}@if($item->school) · {{ $item->school->name }}@endif</span>@endif
                @if($canEdit)
                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                        @if($item->status !== 'resolved')
                            <span class="rounded-full px-2 py-0.5 font-semibold {{ $item->assigned_name ? 'bg-primary/10 text-primary' : 'bg-amber-100 text-amber-800' }}">{{ $item->assigned_name ? '👤 '.\Illuminate\Support\Str::before($item->assigned_name, ' ') : 'Unassigned' }}</span>
                            <span class="{{ $age }}">Open {{ $item->created_at->diffForHumans(null, true, true) }}</span>
                        @endif
                        @if($item->type === 'subscription')<span class="rounded-full bg-surface-container px-2 py-0.5 font-semibold text-on-surface-variant">🧾 Subscription</span>@endif
                    </span>
                @endif
                <span class="mt-1 block break-all text-[10px] text-on-surface-variant">{{ $item->ticket_no }} · {{ $item->created_at->format('M d, g:i A') }}</span>
            </a></li>
        @endforeach
    </ul>
@endif
</div>
