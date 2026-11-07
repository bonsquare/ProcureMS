@php
    $isOwner = (int) $current->user_id === (int) auth()->id();
    $open = \App\Support\SupportStatus::isOpen($current->status);
@endphp
{{-- The thread refreshes itself every few seconds (see the script below), so a new answer shows without reloading the page. --}}
<div id="support-thread" data-poll="{{ route('support.poll', $current) }}" data-sig="{{ $current->thread_signature }}">
    @include('partials.support-thread')
</div>

@if(! $open)
    <p class="mt-4 rounded-lg bg-surface-container px-4 py-3 text-sm text-on-surface-variant">This request was resolved on {{ ($current->resolved_at ?? $current->updated_at)->format('M d, Y') }}. The conversation is removed on {{ ($current->resolved_at ?? $current->updated_at)->copy()->addDays(5)->format('M d, Y') }}. Create a new request if you still need help.</p>
@elseif($isOwner)
    <form method="POST" action="{{ route('support.reply', $current) }}" enctype="multipart/form-data" class="mt-3 rounded-xl border border-outline-variant/50 bg-white p-2 focus-within:border-primary">@csrf
        <label for="reply-body" class="sr-only">Your answer</label>
        <textarea id="reply-body" name="body" rows="2" required maxlength="2000" placeholder="Write your answer to Customer Services." class="w-full resize-y border-0 bg-transparent px-2 py-2 text-sm outline-none">{{ old('body') }}</textarea>
        <div class="flex flex-wrap items-center gap-2 border-t border-outline-variant/30 pt-2">
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-primary hover:bg-primary/5 focus-within:ring-2 focus-within:ring-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">attach_file</span>Attach photo or PDF
                <input id="reply-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="sr-only">
            </label>
            <span id="reply-files-note" class="order-last w-full break-words text-[11px] text-on-surface-variant sm:order-none sm:w-auto sm:min-w-0 sm:flex-1">Up to 3 files, 5 MB each, saved in your Google Drive.</span>
            <button class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>Send answer</button>
        </div>
    </form>
@endif

<script>
    (() => {
        const box = document.getElementById('support-thread');
        if (!box) { return; }
        const messages = () => document.getElementById('support-messages');
        const toBottom = () => { const list = messages(); if (list) { list.scrollTop = list.scrollHeight; } };
        toBottom();

        const files = document.getElementById('reply-files');
        const note = document.getElementById('reply-files-note');
        if (files && note) {
            const idle = note.textContent;
            files.addEventListener('change', () => { note.textContent = files.files.length ? Array.from(files.files).map((file) => file.name).join(', ') : idle; });
        }

        let signature = box.dataset.sig;
        let busy = false;
        const refresh = async () => {
            if (busy || document.hidden) { return; }
            busy = true;
            try {
                const response = await fetch(box.dataset.poll, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!response.ok) { return; }
                const data = await response.json();
                if (data.signature === signature) { return; }
                const list = messages();
                const atBottom = !list || list.scrollHeight - list.scrollTop - list.clientHeight < 80;
                signature = data.signature;
                if (!data.open) { window.location.reload(); return; }
                box.innerHTML = data.html;
                const requests = document.getElementById('support-list');
                if (requests) { requests.innerHTML = data.list; }
                if (atBottom) { toBottom(); }
            } catch (error) {
                // Offline or signed out: try again on the next round.
            } finally {
                busy = false;
            }
        };
        setInterval(refresh, 6000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) { refresh(); } });
    })();
</script>
