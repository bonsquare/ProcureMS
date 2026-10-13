{{-- The strip of a planning document (data entry layer only; the official form is not touched): the form it makes, what it is based on, its status,
     what is still missing before it is generated, and its history. $strip comes from App\Support\DocumentStrip. --}}
@php
    $tones = ['green' => 'bg-secondary/10 text-secondary', 'amber' => 'bg-amber-100 text-amber-800', 'grey' => 'bg-surface-high text-on-surface-variant'];
    $stripId = 'strip-'.\Illuminate\Support\Str::slug($strip['title']);
@endphp
<section id="{{ $stripId }}" class="mb-5 overflow-hidden rounded-xl border border-outline-variant/40 bg-white shadow-sm" aria-label="Document overview">
    <div class="grid gap-4 p-4 md:grid-cols-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Makes the official form</p>
            <p class="mt-1 flex items-center gap-1.5 text-sm font-semibold"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">description</span>{{ $strip['generates'] }}</p>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Based on</p>
            @if($strip['source'])
                <p class="mt-1 text-sm font-semibold"><a href="{{ $strip['source']['url'] }}" class="text-primary hover:underline">{{ $strip['source']['label'] }}</a>@if($strip['source']['status'])<span class="ml-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $strip['source']['status'] === 'approved' ? $tones['green'] : $tones['amber'] }}">{{ \Illuminate\Support\Str::headline($strip['source']['status']) }}</span>@endif</p>
            @else
                <p class="mt-1 text-sm text-on-surface-variant">{{ $strip['sourceNote'] }}</p>
            @endif
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</p>
            <p class="mt-1"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $tones[$strip['tone']] ?? $tones['grey'] }}">{{ $strip['statusLabel'] }}</span></p>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Next</p>
            <p class="mt-1 text-sm font-semibold text-primary">{{ $strip['next'] }}</p>
        </div>
    </div>

    <details class="border-t border-outline-variant/20" data-strip-review>
        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 text-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">fact_check</span>Review before generating</span>
            <span class="{{ $strip['missingRequired'] ? 'text-error' : 'text-secondary' }}">{{ $strip['ready'] }} of {{ count($strip['checks']) }} ready @if($strip['missingRequired']) · {{ $strip['missingRequired'] }} required missing @endif</span>
        </summary>
        <ul class="divide-y divide-outline-variant/15 border-t border-outline-variant/15">
            @foreach($strip['checks'] as $check)
                <li class="flex items-start gap-2.5 px-4 py-2.5 text-sm">
                    @if($check['ok'])
                        <span class="material-symbols-outlined mt-0.5 text-[18px] text-secondary" aria-label="Ready">check_circle</span>
                    @else
                        <span class="material-symbols-outlined mt-0.5 text-[18px] {{ $check['required'] ? 'text-error' : 'text-amber-600' }}" aria-label="{{ $check['required'] ? 'Required' : 'Recommended' }}">{{ $check['required'] ? 'cancel' : 'error' }}</span>
                    @endif
                    <span class="min-w-0 flex-1"><span class="{{ $check['ok'] ? 'text-on-surface-variant' : 'font-semibold' }}">{{ $check['label'] }}</span>@unless($check['ok'])<span class="block text-xs text-on-surface-variant">{{ $check['required'] ? 'Required: ' : 'Recommended: ' }}{{ $check['detail'] }}</span>@endunless</span>
                </li>
            @endforeach
        </ul>
    </details>

    <details class="border-t border-outline-variant/20" data-strip-history>
        <summary class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 text-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">history</span>History</span>
            <span class="text-on-surface-variant">{{ $strip['events']->count() }} recorded event{{ $strip['events']->count() === 1 ? '' : 's' }}</span>
        </summary>
        @forelse($strip['events'] as $event)
            <div class="flex gap-3 border-t border-outline-variant/15 px-4 py-2.5 text-sm">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-baseline justify-between gap-x-3"><span class="font-semibold">{{ $event['what'] }}@if($event['change'])<span class="ml-2 text-xs font-normal text-on-surface-variant">{{ $event['change'] }}</span>@endif</span><span class="text-xs text-on-surface-variant">{{ $event['when'] }} · {{ $event['who'] }}</span></p>
                    @if($event['remarks'])<p class="text-xs text-on-surface-variant">{{ $event['remarks'] }}</p>@endif
                </div>
            </div>
        @empty
            <p class="border-t border-outline-variant/15 px-4 py-3 text-xs text-on-surface-variant">Nothing recorded yet.</p>
        @endforelse
    </details>
</section>
