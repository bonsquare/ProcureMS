{{-- The master user's desk is one window split in two: the queue on the left, the conversation on the right, each scrolling on its own. --}}
<div class="{{ $canEdit ? 'grid grid-cols-1 overflow-hidden rounded-xl border border-outline-variant/40 bg-white shadow-sm md:h-[calc(100vh-13rem)] md:min-h-[34rem] md:grid-cols-[minmax(17rem,22rem)_minmax(0,1fr)] md:divide-x md:divide-outline-variant/40' : 'grid grid-cols-1 gap-4 md:grid-cols-[minmax(15rem,21rem)_minmax(0,1fr)]' }}">
    <aside class="{{ $canEdit ? 'flex min-h-0 min-w-0 flex-col bg-surface/60 p-3' : 'min-w-0 rounded border border-outline-variant/30 bg-white p-4' }}" aria-labelledby="req-list">
        <div class="flex items-center justify-between gap-2">
            <h2 id="req-list" class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">{{ $canEdit ? 'Support queue' : 'Your requests' }}</h2>
            <a href="{{ $canEdit ? route('support', ['new' => 1]) : route('support') }}" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Create request</a>
        </div>
        @if($canEdit)
            <form method="GET" action="{{ $current ? route('support.show', $current) : route('support') }}" role="search" class="mt-3 flex gap-1.5">
                @if(($filter ?? 'open') !== 'open')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                <label class="min-w-0 flex-1"><span class="sr-only">Search requests</span><input type="search" name="q" value="{{ $q ?? '' }}" maxlength="80" placeholder="Search subject, message, person or school" class="w-full rounded-lg border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none focus:border-primary"></label>
                <button class="inline-flex min-h-9 shrink-0 items-center rounded-lg border border-outline-variant/50 bg-white px-2.5 text-primary hover:bg-primary/5" aria-label="Search"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span></button>
            </form>
        @endif
        <div id="support-list" class="{{ $canEdit ? 'mt-3 max-h-[26rem] min-h-0 flex-1 overflow-y-auto pr-1 md:max-h-none' : 'mt-3 md:max-h-[70vh] md:overflow-y-auto md:pr-1' }}">@include('partials.support-list')</div>
    </aside>

    <section class="{{ $canEdit ? 'flex min-h-0 min-w-0 flex-col overflow-y-auto bg-white p-4 md:p-5' : 'min-w-0 rounded border border-outline-variant/30 bg-white p-5' }}" aria-label="{{ $current ? 'Conversation' : 'Create a request' }}">
        @if($current)
            @include('partials.support-conversation')
        @elseif($canEdit && ! request()->boolean('new'))
            <div id="support-overview">@include('partials.support-desk')</div>
        @else
            <h2 class="text-lg font-semibold">Create a request</h2>
            <p class="mt-1 text-xs text-on-surface-variant">Your request goes straight to Customer Services{{ $mailOn ? '' : ' (no Customer Services email is set yet: it is saved only)' }}. Their answer appears here in the conversation.</p>
            <form method="POST" action="{{ route('support.send') }}" enctype="multipart/form-data" class="mt-3">@csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[14rem_minmax(0,1fr)]">
                    <label class="block text-xs font-semibold text-on-surface-variant">Type<select name="type" class="{{ $inputClass }}"><option value="general" @selected(old('type', 'general') === 'general')>General help</option><option value="subscription" @selected(old('type') === 'subscription')>Subscription</option></select>@error('type')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="block text-xs font-semibold text-on-surface-variant">Subject<input name="subject" required maxlength="120" value="{{ old('subject') }}" placeholder="In a few words" class="{{ $inputClass }}">@error('subject')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                </div>
                <label class="mt-3 block text-xs font-semibold text-on-surface-variant">Your message<textarea name="body" rows="4" required minlength="3" maxlength="2000" placeholder="Describe what you need help with, and the page you were on." class="{{ $inputClass }}">{{ old('body') }}</textarea></label>
                <label class="mt-3 block text-xs font-semibold text-on-surface-variant">Photos or PDF (optional, up to 3 files of 5 MB)<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="mt-1 block w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary">@error('files')<span class="mt-1 block text-error">{{ $message }}</span>@enderror @error('files.0')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                <p class="mt-1 text-[11px] text-on-surface-variant">Files are saved in your own Google Drive, not on this system.</p>
                <div class="mt-2 flex justify-end"><button class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>Create request</button></div>
            </form>
        @endif
    </section>
</div>

{{-- The queue and the desk overview refresh themselves, so a request that arrives or changes in Telegram or by another person shows here without reloading. --}}
<script>
    (() => {
        const list = document.getElementById('support-list');
        if (!list) { return; }
        const overview = document.getElementById('support-overview');
        const url = new URL(@json(route('support.queue')), window.location.href);
        const here = new URLSearchParams(window.location.search);
        ['filter', 'q'].forEach((key) => { if (here.get(key)) { url.searchParams.set(key, here.get(key)); } });
        @if($current) url.searchParams.set('current', @json($current->id)); @endif
        let busy = false;
        let lastList = null;
        let lastOverview = null;
        const refresh = async () => {
            if (busy || document.hidden || document.activeElement?.name === 'q') { return; }
            busy = true;
            try {
                const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!response.ok) { return; }
                const data = await response.json();
                if (data.list !== lastList) { list.innerHTML = data.list; lastList = data.list; }
                if (overview && data.overview && data.overview !== lastOverview) { overview.innerHTML = data.overview; lastOverview = data.overview; }
            } catch (error) {
                // Offline or signed out: try again on the next round.
            } finally {
                busy = false;
            }
        };
        setInterval(refresh, 8000);
    })();
</script>
