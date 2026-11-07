@php
    $chipClass = fn (string $status) => ['for_process' => 'bg-secondary/10 text-secondary', 'approved' => 'bg-emerald-100 text-emerald-800', 'on_hold' => 'bg-slate-200 text-slate-700', 'resolved' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-amber-100 text-amber-800';
@endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(15rem,21rem)_minmax(0,1fr)]">
    <aside class="min-w-0 rounded border border-outline-variant/30 bg-white p-4" aria-labelledby="req-list">
        <div class="flex items-center justify-between gap-2">
            <h2 id="req-list" class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">{{ $canEdit ? 'All requests' : 'Your requests' }}</h2>
            <a href="{{ route('support') }}" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Create request</a>
        </div>
        @if($messages->isEmpty())
            <p class="mt-3 text-sm text-on-surface-variant">No requests yet. Create one and Customer Services will answer here.</p>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($messages as $item)
                    <li><a href="{{ route('support.show', $item) }}" @if($current && $current->id === $item->id) aria-current="page" @endif class="block rounded-xl border p-3 hover:bg-surface-low {{ $current && $current->id === $item->id ? 'border-primary bg-primary/5' : 'border-outline-variant/40' }}">
                        <span class="block break-words text-sm font-semibold">#{{ $item->id }} · {{ $item->subject ?: 'Support request' }}</span>
                        @if($canEdit)<span class="block break-words text-[11px] text-on-surface-variant">{{ $item->user?->name }}@if($item->school) · {{ $item->school->name }}@endif</span>@endif
                        <span class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-on-surface-variant"><span class="rounded-full px-2 py-0.5 font-bold {{ $chipClass($item->status) }}">{{ \App\Support\SupportStatus::label($item->status) }}</span><span>{{ $item->created_at->format('M d, Y g:i A') }}</span></span>
                    </a></li>
                @endforeach
            </ul>
        @endif
    </aside>

    <section class="min-w-0 rounded border border-outline-variant/30 bg-white p-5" aria-label="{{ $current ? 'Conversation' : 'Create a request' }}">
        @if($current)
            @include('partials.support-conversation')
        @else
            <h2 class="text-lg font-semibold">Create a request</h2>
            <p class="mt-1 text-xs text-on-surface-variant">Your request goes straight to Customer Services{{ $mailOn ? '' : ' (no Customer Services email is set yet: it is saved only)' }}. Their answer appears here in the conversation.</p>
            <form method="POST" action="{{ route('support.send') }}" class="mt-3">@csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[14rem_minmax(0,1fr)]">
                    <label class="block text-xs font-semibold text-on-surface-variant">Type<select name="type" class="{{ $inputClass }}"><option value="general" @selected(old('type', 'general') === 'general')>General help</option><option value="subscription" @selected(old('type') === 'subscription')>Subscription</option></select>@error('type')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="block text-xs font-semibold text-on-surface-variant">Subject<input name="subject" required maxlength="120" value="{{ old('subject') }}" placeholder="In a few words" class="{{ $inputClass }}">@error('subject')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                </div>
                <label class="mt-3 block text-xs font-semibold text-on-surface-variant">Your message<textarea name="body" rows="4" required minlength="3" maxlength="2000" placeholder="Describe what you need help with, and the page you were on." class="{{ $inputClass }}">{{ old('body') }}</textarea></label>
                <div class="mt-2 flex justify-end"><button class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>Create request</button></div>
            </form>
        @endif
    </section>
</div>
