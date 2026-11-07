@once
{{-- Extra protection for an edit, update or delete: a form with data-guard="Title" opens this window and asks for a reason and the user's own 6-digit PIN before it is sent. Script code can ask the same with `await window.actionGuard('Title')`, which gives {reason, pin} or null. A person with no PIN, or who forgot it, sets a new one here with the login password. A button with data-history="url" lists what was done to that record. --}}
<dialog id="action-guard" aria-labelledby="action-guard-title" aria-describedby="action-guard-intro" style="width:min(30rem,calc(100% - 2rem))" class="rounded-2xl border border-outline-variant/40 bg-white p-0 text-left shadow-2xl backdrop:bg-black/50">
    <form method="dialog" class="p-6" id="action-guard-form">
        <div class="mb-5 flex items-start gap-3">
            <span id="action-guard-badge" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary" aria-hidden="true"><span class="material-symbols-outlined">verified_user</span></span>
            <div class="min-w-0 flex-1"><h2 id="action-guard-title" class="text-lg font-bold leading-tight">Confirm the change</h2><p id="action-guard-intro" class="mt-1 text-xs leading-5 text-on-surface-variant">Write the reason and type your own 6-digit PIN. Both are kept in the history of the record.</p></div>
            <button type="button" id="action-guard-x" class="grid h-11 w-11 shrink-0 place-items-center rounded-lg hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" aria-label="Close"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
        <div class="space-y-4">
            <div>
                <label for="action-guard-reason" class="block text-xs font-bold text-on-surface-variant">Reason for the change <span class="text-error" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                <input id="action-guard-reason" required maxlength="255" autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" placeholder="e.g. Corrected the payee name" class="mt-1.5 w-full rounded-lg border border-outline-variant/60 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
            </div>
            <div id="action-guard-pin-wrap">@include('partials.pin-boxes', ['name' => 'guard_pin', 'label' => 'Your PIN (6 digits)'])</div>
            <button type="button" id="action-guard-forgot" class="inline-flex min-h-11 items-center text-xs font-semibold text-primary underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">Forgot your PIN? Reset it with your password</button>
            <div id="action-guard-setpin" class="hidden space-y-4 rounded-xl border border-outline-variant/40 bg-surface-low p-4">
                <p id="action-guard-setpin-note" class="text-xs font-semibold text-on-surface-variant">Choose a new 6-digit PIN. Your login password proves it is you.</p>
                @include('partials.pin-boxes', ['name' => 'reset_pin', 'label' => 'New PIN (6 digits)', 'optional' => true])
                @include('partials.pin-boxes', ['name' => 'reset_pin_confirmation', 'label' => 'Repeat the new PIN', 'optional' => true, 'matchId' => 'action-guard-reset-match'])
                <div>
                    <label for="action-guard-loginpw" class="block text-xs font-bold text-on-surface-variant">Your login password</label>
                    <input id="action-guard-loginpw" type="password" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" class="mt-1.5 w-full rounded-lg border border-outline-variant/60 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                </div>
                <div class="flex justify-end"><button type="button" id="action-guard-savepin" class="min-h-11 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container">Save PIN</button></div>
            </div>
            <p id="action-guard-message" role="status" class="hidden items-start gap-2 rounded-lg px-3 py-2.5 text-xs font-semibold"></p>
        </div>
        <div class="mt-6 flex justify-end gap-2 border-t border-outline-variant/30 pt-4"><button type="button" id="action-guard-cancel" class="min-h-11 rounded-lg border border-outline-variant/60 px-5 py-2 text-xs font-semibold hover:bg-surface-container">Close</button><button id="action-guard-ok" class="min-h-11 rounded-lg bg-primary px-6 py-2 text-xs font-bold text-white hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">Confirm</button></div>
    </form>
</dialog>
<dialog id="action-history" style="width:min(36rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50">
    <div class="flex max-h-[85vh] flex-col">
        <div class="flex items-center justify-between border-b border-outline-variant/30 px-5 py-3"><h2 id="action-history-title" class="text-lg font-semibold">History</h2><button type="button" onclick="this.closest('dialog').close()" aria-label="Close" class="rounded p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
        <ol id="action-history-list" class="space-y-3 overflow-y-auto p-5 text-sm"></ol>
    </div>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('action-guard');
        const form = document.getElementById('action-guard-form');
        const reason = document.getElementById('action-guard-reason');
        const pinWrap = document.getElementById('action-guard-pin-wrap');
        const guardBoxes = [...pinWrap.querySelectorAll('[data-pin-box]')];
        const setPanel = document.getElementById('action-guard-setpin');
        const setNote = document.getElementById('action-guard-setpin-note');
        const message = document.getElementById('action-guard-message');
        const forgot = document.getElementById('action-guard-forgot');
        const confirmButton = document.getElementById('action-guard-ok');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let hasPin = @json(filled(auth()->user()?->pin));
        let target = null;
        let pending = null;

        const clearBoxes = (root) => {
            root.querySelectorAll('[data-pin-box]').forEach((box) => { box.value = ''; box.classList.remove('pin-filled'); });
            root.querySelectorAll('input[type=hidden]').forEach((input) => { input.value = ''; });
            root.querySelector('[data-pin-match]')?.replaceChildren();
        };
        const say = (text, ok) => {
            message.textContent = text;
            message.classList.toggle('hidden', ! text);
            message.classList.toggle('flex', !! text);
            message.classList.toggle('bg-error/10', ! ok);
            message.classList.toggle('text-error', ! ok);
            message.classList.toggle('bg-secondary/10', !! ok);
            message.classList.toggle('text-secondary', !! ok);
        };
        const showSetPanel = (open) => {
            setPanel.classList.toggle('hidden', ! open);
            forgot.classList.toggle('hidden', ! hasPin || open);
            pinWrap.classList.toggle('hidden', ! hasPin);
            guardBoxes.forEach((box) => { box.required = hasPin; });
            confirmButton.disabled = ! hasPin;
            clearBoxes(setPanel);
            document.getElementById('action-guard-loginpw').value = '';
            setNote.textContent = hasPin ? 'Choose a new 6-digit PIN. Your login password proves it is you.' : 'You have no PIN yet. Set a 6-digit PIN now; your login password proves it is you.';
            if (open) setPanel.querySelector('[data-pin-box]')?.focus();
        };
        const open = (title) => {
            const destructive = /^(delete|remove|cancel)\b/i.test(title);
            document.getElementById('action-guard-title').textContent = title;
            confirmButton.classList.toggle('bg-error', destructive);
            confirmButton.classList.toggle('hover:opacity-90', destructive);
            confirmButton.classList.toggle('bg-primary', ! destructive);
            confirmButton.classList.toggle('hover:bg-primary-container', ! destructive);
            document.getElementById('action-guard-badge').querySelector('span').textContent = destructive ? 'warning' : 'verified_user';
            document.getElementById('action-guard-badge').classList.toggle('bg-error/10', destructive);
            document.getElementById('action-guard-badge').classList.toggle('text-error', destructive);
            document.getElementById('action-guard-badge').classList.toggle('bg-primary/10', ! destructive);
            document.getElementById('action-guard-badge').classList.toggle('text-primary', ! destructive);
            reason.value = ''; clearBoxes(pinWrap); say('');
            showSetPanel(! hasPin);
            dialog.showModal();
            // The window rises into place; none of it when the person asks for less motion.
            if (! reduceMotion.matches && dialog.animate) dialog.animate([{ opacity: 0, transform: 'translateY(10px) scale(.98)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(.2,.8,.2,1)' });
            (hasPin ? reason : setPanel.querySelector('[data-pin-box]'))?.focus();
        };
        const pinValue = () => guardBoxes.map((box) => box.value).join('');

        const field = (formElement, name) => formElement.querySelector('input[type=hidden][name="' + name + '"]') ?? formElement.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name }));
        document.addEventListener('submit', (event) => {
            const guarded = event.target;
            if (! guarded.matches || ! guarded.matches('form[data-guard]') || guarded.dataset.guarded === '1') return;
            event.preventDefault();
            event.stopImmediatePropagation(); // the old confirm() of the form is replaced by this window
            target = guarded;
            open(guarded.dataset.guard);
        }, true);
        window.actionGuard = (title) => new Promise((resolve) => { pending = resolve; target = null; open(title); });

        const close = () => dialog.close();
        document.getElementById('action-guard-cancel').addEventListener('click', close);
        document.getElementById('action-guard-x').addEventListener('click', close);
        dialog.addEventListener('close', () => { if (pending) { pending(null); pending = null; } clearBoxes(pinWrap); });
        forgot.addEventListener('click', () => showSetPanel(true));

        document.getElementById('action-guard-savepin').addEventListener('click', async () => {
            say('');
            const [first, second] = [...setPanel.querySelectorAll('input[type=hidden]')];
            try {
                const response = await fetch(@json(route('account.pin')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || @json(csrf_token()) },
                    body: JSON.stringify({ pin: first.value, pin_confirmation: second.value, password: document.getElementById('action-guard-loginpw').value }),
                });
                const data = await response.json().catch(() => ({}));
                if (! response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'The PIN could not be saved.');
                hasPin = true;
                showSetPanel(false);
                say('Your PIN was saved. Type it above to confirm.', true);
                guardBoxes[0]?.focus();
            } catch (error) {
                say(error.message, false);
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const pin = pinValue();
            if (! reason.value.trim() || ! /^\d{6}$/.test(pin)) { say('Write the reason and type all 6 digits of your PIN.', false); return; }
            const answer = { reason: reason.value.trim(), pin };
            if (pending) {
                const done = pending; pending = null;
                dialog.close();
                done(answer);
                return;
            }
            if (! target) return;
            field(target, 'reason').value = answer.reason;
            field(target, 'pin').value = answer.pin;
            target.dataset.guarded = '1';
            dialog.close();
            window.appBusy?.(target);
            target.submit();
        });

        const list = document.getElementById('action-history-list');
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-history]');
            if (! button) return;
            list.innerHTML = '<li class="text-on-surface-variant">Loading…</li>';
            document.getElementById('action-history-title').textContent = 'History';
            document.getElementById('action-history').showModal();
            try {
                const response = await fetch(button.dataset.history, { headers: { Accept: 'application/json' } });
                if (! response.ok) throw new Error('failed');
                const data = await response.json();
                document.getElementById('action-history-title').textContent = 'History · ' + data.title;
                list.innerHTML = '';
                if (! data.entries.length) list.innerHTML = '<li class="text-on-surface-variant">Nothing has been recorded for this record yet.</li>';
                data.entries.forEach((entry) => {
                    const item = document.createElement('li');
                    item.className = 'flex gap-3 rounded-lg border border-outline-variant/40 p-3';
                    const icon = Object.assign(document.createElement('span'), { className: 'material-symbols-outlined text-[20px] text-primary', textContent: entry.icon });
                    const body = document.createElement('div');
                    body.className = 'min-w-0';
                    const head = Object.assign(document.createElement('p'), { className: 'font-semibold', textContent: entry.action });
                    const meta = Object.assign(document.createElement('p'), { className: 'text-xs text-on-surface-variant', textContent: entry.who + ' · ' + entry.when });
                    body.append(head, meta);
                    if (entry.reason) body.append(Object.assign(document.createElement('p'), { className: 'mt-1 text-xs', textContent: 'Reason: ' + entry.reason }));
                    entry.details.forEach((line) => body.append(Object.assign(document.createElement('p'), { className: 'mt-1 break-words text-[11px] text-on-surface-variant', textContent: line })));
                    item.append(icon, body);
                    list.append(item);
                });
            } catch (error) {
                list.innerHTML = '<li class="text-error">The history could not be loaded.</li>';
            }
        });
    })();
</script>
@endonce
