@php
    $chipClass = fn (string $status) => ['for_process' => 'bg-secondary/10 text-secondary', 'approved' => 'bg-emerald-100 text-emerald-800', 'on_hold' => 'bg-slate-200 text-slate-700', 'resolved' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-amber-100 text-amber-800';
@endphp
@if($messages->isEmpty())
    <p class="text-sm text-on-surface-variant">No requests yet. Create one and Customer Services will answer here.</p>
@else
    <ul class="space-y-2">
        @foreach($messages as $item)
            <li><a href="{{ route('support.show', $item) }}" @if($current && $current->id === $item->id) aria-current="page" @endif class="block rounded-xl border p-3 hover:bg-surface-low {{ $current && $current->id === $item->id ? 'border-primary bg-primary/5' : 'border-outline-variant/40' }}">
                <span class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                    <span class="min-w-0 flex-1 break-words text-sm font-semibold">{{ $item->subject ?: 'Support request' }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $chipClass($item->status) }}">{{ \App\Support\SupportStatus::label($item->status) }}</span>
                </span>
                @if($canEdit)<span class="mt-0.5 block break-words text-[11px] text-on-surface-variant">{{ $item->user?->name }}@if($item->school) · {{ $item->school->name }}@endif</span>@endif
                <span class="mt-1 block break-all text-[11px] text-on-surface-variant">{{ $item->ticket_no }}</span>
                <span class="block text-[11px] text-on-surface-variant">{{ $item->created_at->format('M d, Y g:i A') }}</span>
            </a></li>
        @endforeach
    </ul>
@endif
