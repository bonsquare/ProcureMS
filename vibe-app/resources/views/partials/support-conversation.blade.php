@php
    $isOwner = (int) $current->user_id === (int) auth()->id();
    $open = \App\Support\SupportStatus::isOpen($current->status);
@endphp
{{-- The thread refreshes itself every few seconds (see the script below), so a new answer shows without reloading the page. --}}
<div id="support-thread" class="{{ $canEdit ? 'flex min-h-0 flex-1 flex-col' : '' }}" data-poll="{{ route('support.poll', ['supportMessage' => $current->id] + array_filter(['filter' => ($filter ?? 'open') !== 'open' ? $filter : null, 'q' => ($q ?? '') !== '' ? $q : null])) }}" data-sig="{{ $current->thread_signature }}">
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
@elseif($canEdit)
    @php
        $telegramOn = app(\App\Services\TelegramService::class)->configured();
        $currentFor = ['pending' => 'pending', 'process' => 'for_process', 'hold' => 'on_hold', 'approve' => 'approved', 'resolve' => 'resolved', 'close' => 'resolved'];
        $iHoldIt = $current->assigned_name !== null && $current->assigned_name === auth()->user()->name;
    @endphp
    <section class="mt-3 min-w-0 shrink-0 rounded-xl border border-primary/30 bg-primary/5 p-3" aria-label="Customer Services actions">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="text-xs font-bold uppercase tracking-wide text-primary">Customer Services</p>
            <p class="min-w-0 flex-1 break-words text-[11px] text-on-surface-variant">{{ $telegramOn ? 'Linked with Telegram: what you do here is posted in the chat, and answers written in Telegram appear here.' : 'Telegram is not connected: your answers and changes are saved here only.' }}</p>
        </div>
        <form method="POST" action="{{ route('support.status', $current) }}" class="mt-2 flex flex-wrap gap-1.5">@csrf
            @foreach([['take', '🙋 Take it'], ['pending', '🟡 Pending'], ['process', '▶️ On process'], ['hold', '⏸ On hold'], ['approve', '👍 Approve'], ['resolve', '✅ Resolve'], ['close', '🔒 Close']] as [$action, $label])
                @php $isCurrent = $action === 'take' ? $iHoldIt : ($currentFor[$action] === $current->status && ! in_array($action, ['close'], true)); @endphp
                <button name="action" value="{{ $action }}" @if($isCurrent) aria-current="true" @endif class="inline-flex min-h-9 items-center gap-1 rounded-lg border bg-white px-3 py-1.5 text-xs font-semibold hover:bg-surface-low {{ $isCurrent ? 'border-primary ring-2 ring-primary/40' : 'border-outline-variant/50' }} {{ $action === 'approve' ? 'text-emerald-800' : '' }}">{{ $label }}</button>
            @endforeach
        </form>
        <form method="POST" action="{{ route('support.assign', $current) }}" class="mt-2 flex flex-wrap items-center gap-2">@csrf
            <label for="assign-to" class="shrink-0 text-[11px] font-semibold text-on-surface-variant">Assign to</label>
            <select id="assign-to" name="staff" class="min-h-9 min-w-0 flex-1 rounded-lg border border-outline-variant/50 bg-white px-2 py-1.5 text-xs outline-none focus:border-primary">
                <option value="none" @selected(! $current->assigned_name)>Nobody (back in the queue)</option>
                <option value="me" @selected($iHoldIt)>Me ({{ auth()->user()->name }})</option>
                @foreach(($staffList ?? []) as $staffId => $staffName)
                    <option value="{{ $staffId }}" @selected(! $iHoldIt && (int) $current->assigned_telegram_id === (int) $staffId && $current->assigned_name === $staffName)>{{ $staffName }} (Telegram)</option>
                @endforeach
            </select>
            <button class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-lg border border-primary/40 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">person_add</span>Assign</button>
        </form>
        @if($current->type === 'subscription')
            <p class="mt-2 break-words text-[11px] text-on-surface-variant">This is a subscription request. Press Approve once it is accepted; payment proofs themselves are approved on the <a href="{{ route('subscriptions') }}" class="font-semibold text-primary underline">Subscriptions page</a>.</p>
        @endif
        <form method="POST" action="{{ route('support.staff-reply', $current) }}" class="mt-3 rounded-xl border border-outline-variant/50 bg-white p-2 focus-within:border-primary">@csrf
            @php
                $quickReplies = [
                    'We received your request' => 'Hello! We received your request and we are checking it now. We will update you here.',
                    'Ask for a screenshot' => 'Please send a screenshot of the page and the steps you took, so we can check it.',
                    'Subscription payment' => 'To renew or activate the subscription, please send your payment and upload the proof on the Subscription page. We will review it right away.',
                    'Password reset' => 'For your safety, passwords are not sent by chat. Please ask your school administrator to reset your password.',
                    'It is fixed' => 'This is now fixed. Please try again and tell us if the problem continues.',
                ];
            @endphp
            <label class="flex flex-wrap items-center gap-2 px-1 pb-1 text-[11px] font-semibold text-on-surface-variant">Quick reply
                <select class="min-h-8 min-w-0 flex-1 rounded-lg border border-outline-variant/50 bg-white px-2 py-1 text-xs font-normal outline-none focus:border-primary" onchange="if (this.value) { const box = document.getElementById('staff-reply-body'); box.value = (box.value ? box.value + ' ' : '') + this.value; box.focus(); this.selectedIndex = 0; }">
                    <option value="">Insert a ready answer…</option>
                    @foreach($quickReplies as $label => $text)<option value="{{ $text }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label for="staff-reply-body" class="sr-only">Your answer to the requester</label>
            <textarea id="staff-reply-body" name="body" rows="2" required maxlength="2000" placeholder="Write your answer to {{ $current->user?->name ?? 'the requester' }}." class="w-full resize-y border-0 bg-transparent px-2 py-2 text-sm outline-none">{{ old('body') }}</textarea>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-outline-variant/30 pt-2">
                <span class="min-w-0 flex-1 break-words text-[11px] text-on-surface-variant">The first answer moves a pending request to On process.</span>
                <button class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>Send answer</button>
            </div>
        </form>
    </section>
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
