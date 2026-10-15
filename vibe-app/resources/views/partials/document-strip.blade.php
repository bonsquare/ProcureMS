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
            @if(! empty($reopenUrl))
                <form method="POST" action="{{ $reopenUrl }}" class="mt-2" onsubmit="return confirm({{ \Illuminate\Support\Js::from($reopenConfirm ?? 'Reopen this SOB as a draft? The budget lines keep their amounts until you approve it again.') }})">@csrf<button class="inline-flex items-center gap-1.5 rounded-lg border border-amber-500 bg-amber-300 px-4 py-2 text-xs font-extrabold text-amber-950 shadow-sm transition hover:bg-amber-400"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">lock_open</span>Reopen as a draft</button></form>
            @endif
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

    {{-- Review and History open in a card window, so the page stays short. --}}
    <div class="grid gap-3 border-t border-outline-variant/20 bg-surface-low/40 p-4 sm:grid-cols-2">
        <button type="button" data-strip-review onclick="document.getElementById('{{ $stripId }}-review').showModal()" class="flex cursor-pointer items-center gap-3 rounded-xl border border-outline-variant/40 bg-white px-4 py-3 text-left shadow-sm transition hover:border-primary hover:shadow-md">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">fact_check</span></span>
            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-primary">Review before generating</span><span class="block text-xs {{ $strip['missingRequired'] ? 'text-error' : 'text-secondary' }}">{{ $strip['ready'] }} of {{ count($strip['checks']) }} ready @if($strip['missingRequired']) · {{ $strip['missingRequired'] }} required missing @endif</span></span>
            <span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant" aria-hidden="true">open_in_new</span>
        </button>
        <button type="button" data-strip-history onclick="document.getElementById('{{ $stripId }}-history').showModal()" class="flex cursor-pointer items-center gap-3 rounded-xl border border-outline-variant/40 bg-white px-4 py-3 text-left shadow-sm transition hover:border-primary hover:shadow-md">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">history</span></span>
            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-primary">History</span><span class="block text-xs text-on-surface-variant">{{ $strip['events']->count() }} recorded event{{ $strip['events']->count() === 1 ? '' : 's' }}</span></span>
            <span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant" aria-hidden="true">open_in_new</span>
        </button>
    </div>

    <dialog id="{{ $stripId }}-review" data-native onclick="if (event.target === this) this.close()" class="w-[min(94vw,36rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <div class="flex items-center justify-between gap-3 border-b border-outline-variant/30 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">
            <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-low text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">fact_check</span></span><div><h3 class="text-base font-semibold">Review before generating</h3><p class="text-xs {{ $strip['missingRequired'] ? 'text-error' : 'text-secondary' }}">{{ $strip['ready'] }} of {{ count($strip['checks']) }} ready @if($strip['missingRequired']) · {{ $strip['missingRequired'] }} required missing @endif</p></div></div>
            <form method="dialog"><button aria-label="Close" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-low"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span></button></form>
        </div>
        <ul class="max-h-[65vh] divide-y divide-outline-variant/15 overflow-y-auto">
            @foreach($strip['checks'] as $check)
                <li class="flex items-start gap-2.5 px-5 py-3 text-sm">
                    @if($check['ok'])
                        <span class="material-symbols-outlined mt-0.5 text-[18px] text-secondary" aria-label="Ready">check_circle</span>
                    @else
                        <span class="material-symbols-outlined mt-0.5 text-[18px] {{ $check['required'] ? 'text-error' : 'text-amber-600' }}" aria-label="{{ $check['required'] ? 'Required' : 'Recommended' }}">{{ $check['required'] ? 'cancel' : 'error' }}</span>
                    @endif
                    <span class="min-w-0 flex-1"><span class="{{ $check['ok'] ? 'text-on-surface-variant' : 'font-semibold' }}">{{ $check['label'] }}</span>@unless($check['ok'])<span class="block text-xs text-on-surface-variant">{{ $check['required'] ? 'Required: ' : 'Recommended: ' }}{{ $check['detail'] }}</span>@endunless</span>
                </li>
            @endforeach
        </ul>
    </dialog>

    <dialog id="{{ $stripId }}-history" data-native onclick="if (event.target === this) this.close()" class="w-[min(94vw,36rem)] rounded-2xl border border-outline-variant/60 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <div class="flex items-center justify-between gap-3 border-b border-outline-variant/30 bg-gradient-to-r from-primary/5 to-transparent px-5 py-4">
            <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-low text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">history</span></span><div><h3 class="text-base font-semibold">History</h3><p class="text-xs text-on-surface-variant">{{ $strip['events']->count() }} recorded event{{ $strip['events']->count() === 1 ? '' : 's' }}</p></div></div>
            <form method="dialog"><button aria-label="Close" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-low"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span></button></form>
        </div>
        <div class="max-h-[65vh] overflow-y-auto">
            @forelse($strip['events'] as $event)
                <div class="flex gap-3 border-b border-outline-variant/15 px-5 py-3 text-sm last:border-0">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $event['what'] }}@if($event['change'])<span class="ml-2 text-xs font-normal text-on-surface-variant">{{ $event['change'] }}</span>@endif</p>
                        <p class="text-xs text-on-surface-variant">{{ $event['when'] }} · {{ $event['who'] }}</p>
                        @if($event['remarks'])<p class="mt-0.5 text-xs text-on-surface-variant">{{ $event['remarks'] }}</p>@endif
                    </div>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-on-surface-variant">Nothing recorded yet.</p>
            @endforelse
        </div>
    </dialog>
</section>
