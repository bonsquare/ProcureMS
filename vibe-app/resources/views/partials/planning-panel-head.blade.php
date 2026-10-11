{{-- Heading of a Planning panel (SIP, AIP, SOB, APP, APP-CSE, Settings). Expects $icon, $title, $text and optionally $chips (short facts such as counts and totals). The action buttons stay beside it in the panel. --}}
<div class="flex min-w-0 items-start gap-3">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ $icon }}</span></span>
    <div class="min-w-0">
        <h2 class="text-base font-semibold tracking-tight">{{ $title }}</h2>
        <p class="mt-0.5 max-w-3xl text-xs leading-5 text-on-surface-variant">{{ $text }}</p>
        @if(!empty($chips))
            <div class="mt-2 flex flex-wrap gap-1.5">@foreach($chips as $chip)<span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $chip }}</span>@endforeach</div>
        @endif
    </div>
</div>
