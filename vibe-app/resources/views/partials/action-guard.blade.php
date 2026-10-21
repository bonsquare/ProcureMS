{{-- Extra protection for an edit, update or delete of an ORS, DV or payment: a form with data-guard="Title" asks for a reason and the user's own password before it is sent. A button with data-history="url" lists what was done to that record. --}}
<dialog id="action-guard" style="width:min(28rem,calc(100% - 2rem))" class="rounded bg-white p-0 text-left shadow-2xl backdrop:bg-black/50">
    <form method="dialog" class="space-y-3 p-6" id="action-guard-form">
        <h2 id="action-guard-title" class="text-lg font-semibold">Confirm the change</h2>
        <p class="text-xs text-on-surface-variant">Write the reason and type your own password. Both are kept in the history of the record.</p>
        <label class="block text-xs font-semibold text-on-surface-variant">Reason <span class="text-error">*</span><input id="action-guard-reason" required maxlength="255" autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm font-normal outline-none focus:border-primary"></label>
        <label class="block text-xs font-semibold text-on-surface-variant">Your password <span class="text-error">*</span><input id="action-guard-password" type="password" required autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-2.5 py-2 text-sm font-normal outline-none focus:border-primary"></label>
        <div class="flex justify-end gap-2 border-t border-outline-variant/30 pt-3"><button type="button" id="action-guard-cancel" class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Close</button><button id="action-guard-ok" class="rounded bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Confirm</button></div>
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
        const reason = document.getElementById('action-guard-reason');
        const password = document.getElementById('action-guard-password');
        let target = null;
        const field = (form, name) => form.querySelector('input[type=hidden][name="' + name + '"]') ?? form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name }));
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (! form.matches || ! form.matches('form[data-guard]') || form.dataset.guarded === '1') return;
            event.preventDefault();
            target = form;
            document.getElementById('action-guard-title').textContent = form.dataset.guard;
            reason.value = ''; password.value = '';
            dialog.showModal();
        }, true);
        document.getElementById('action-guard-cancel').addEventListener('click', () => dialog.close());
        document.getElementById('action-guard-form').addEventListener('submit', (event) => {
            event.preventDefault();
            if (! target || ! reason.value.trim() || ! password.value) return;
            field(target, 'reason').value = reason.value.trim();
            field(target, 'password').value = password.value;
            target.dataset.guarded = '1';
            password.value = '';
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
