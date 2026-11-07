<div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(15rem,21rem)_minmax(0,1fr)]">
    <aside class="min-w-0 rounded border border-outline-variant/30 bg-white p-4" aria-labelledby="req-list">
        <div class="flex items-center justify-between gap-2">
            <h2 id="req-list" class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">{{ $canEdit ? 'All requests' : 'Your requests' }}</h2>
            <a href="{{ route('support') }}" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>Create request</a>
        </div>
        <div id="support-list" class="mt-3 md:max-h-[70vh] md:overflow-y-auto md:pr-1">@include('partials.support-list')</div>
    </aside>

    <section class="min-w-0 rounded border border-outline-variant/30 bg-white p-5" aria-label="{{ $current ? 'Conversation' : 'Create a request' }}">
        @if($current)
            @include('partials.support-conversation')
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
