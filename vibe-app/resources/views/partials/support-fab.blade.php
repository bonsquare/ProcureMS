@unless(request()->routeIs('support'))
<style>
    /* Where the chat sits: bottom-right, lifted above a bottom banner, and beside the docked document preview. */
    :root{--fab-b:1rem;--fab-r:1rem}
    body:not(.dock-open):has(#doc-dock-open){--fab-b:4.5rem}
    #support-fab{bottom:calc(var(--fab-b) + var(--fab-extra,0px));right:var(--fab-r)}
    #support-toast{bottom:calc(var(--fab-b) + var(--fab-extra,0px));right:calc(var(--fab-r) + 3.25rem)}
    #support-chat{bottom:calc(var(--fab-b) + var(--fab-extra,0px) + 3.75rem);right:var(--fab-r);height:min(32rem,calc(100vh - 8.25rem - var(--fab-b) - var(--fab-extra,0px)))}
    @media(min-width:1100px){body.dock-open{--fab-r:calc(var(--dock-w,46vw) + 1rem)}}
    @media(max-width:1099px){body.dock-open #support-fab,body.dock-open #support-chat,body.dock-open #support-toast{display:none}}
    /* Motion: the window grows out of the icon, the bubble slides in, a new message makes the icon ring. Off for people who ask for reduced motion. */
    @keyframes sc-pop{from{opacity:0;transform:translateY(14px) scale(.9)}to{opacity:1;transform:none}}
    @keyframes sc-out{to{opacity:0;transform:translateY(10px) scale(.94)}}
    @keyframes sc-slide{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:none}}
    @keyframes sc-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    @keyframes sc-badge{0%{transform:scale(0)}60%{transform:scale(1.3)}100%{transform:scale(1)}}
    @keyframes sc-border{0%,100%{box-shadow:0 0 0 2px #2aa86b,0 0 0 0 rgba(42,168,107,.45)}50%{box-shadow:0 0 0 2px #2aa86b,0 0 0 6px rgba(42,168,107,0)}}
    @keyframes sc-ring{0%{box-shadow:0 0 0 0 rgba(20,129,143,.55)}100%{box-shadow:0 0 0 16px rgba(20,129,143,0)}}
    @keyframes sc-wiggle{0%,100%{transform:rotate(0)}20%{transform:rotate(-12deg)}40%{transform:rotate(10deg)}60%{transform:rotate(-6deg)}80%{transform:rotate(4deg)}}
    #support-fab{transition:transform .15s ease,background-color .15s ease;animation:sc-pop .3s cubic-bezier(.2,.8,.2,1)}
    #support-fab:hover{transform:scale(1.08)}
    #support-fab:active{transform:scale(.95)}
    #support-fab.sc-alert{animation:sc-ring 1.6s ease-out infinite}
    #support-fab.sc-alert>span:first-child{animation:sc-wiggle 1.6s ease-in-out infinite;transform-origin:50% 10%}
    #support-badge:not(.hidden){animation:sc-badge .35s cubic-bezier(.2,.8,.2,1)}
    #support-chat{transform-origin:bottom right;animation:sc-pop .24s cubic-bezier(.2,.8,.2,1)}
    #support-chat.sc-closing{animation:sc-out .15s ease-in forwards}
    #support-toast:not(.hidden){animation:sc-slide .28s cubic-bezier(.2,.8,.2,1)}
    #support-chat-list button.sc-updated{box-shadow:0 0 0 2px #2aa86b;animation:sc-border 1.4s ease-in-out infinite}
    #support-chat-log>li:last-child,#support-chat-list>li{animation:sc-in .22s ease-out}
    @media(prefers-reduced-motion:reduce){#support-fab,#support-fab.sc-alert,#support-fab.sc-alert>span:first-child,#support-badge,#support-chat,#support-chat.sc-closing,#support-toast,#support-chat-log>li,#support-chat-list>li,#support-chat-list button.sc-updated{animation:none!important;transition:none!important}}
</style>
<button type="button" id="support-fab" class="fixed z-30 hidden h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-lg hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary print:hidden" aria-label="Open Support chat" aria-expanded="false" aria-controls="support-chat" title="Support"><span class="material-symbols-outlined text-[24px]" aria-hidden="true">support_agent</span><span id="support-badge" class="absolute -right-1 -top-1 hidden min-w-5 rounded-full bg-error px-1.5 text-center text-[11px] font-bold leading-5 text-white" aria-hidden="true">1</span></button>
<div id="support-toast" role="status" class="fixed z-30 hidden w-64 max-w-[calc(100vw-6rem)] cursor-pointer rounded-2xl rounded-br-sm bg-white px-3 py-2 text-xs shadow-xl ring-1 ring-primary/30 print:hidden"><p class="font-bold text-primary">Customer Services</p><p id="support-toast-text" class="mt-0.5 line-clamp-2 break-words text-on-surface-variant"></p></div>
<section id="support-chat" class="fixed z-30 hidden w-[22rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-outline-variant/40 bg-white shadow-2xl print:hidden" role="region" aria-label="Support chat">
    <header class="flex shrink-0 items-center gap-2 bg-primary px-3 py-3 text-white">
        <button type="button" id="support-chat-back" class="hidden shrink-0 rounded-full p-1 hover:bg-white/15" aria-label="Back to my requests"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span></button>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/20"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">support_agent</span></span>
        <div class="min-w-0 flex-1"><p class="text-sm font-bold">Customer Services</p><p id="support-chat-status" class="break-words text-[11px] text-white/80">My open requests</p></div>
        <button type="button" id="support-chat-close" class="shrink-0 rounded-full p-1 hover:bg-white/15" aria-label="Close Support chat"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span></button>
    </header>
    <div id="support-chat-info" class="hidden shrink-0 border-b border-outline-variant/30 bg-white px-3 py-2"><p id="support-info-subject" class="line-clamp-2 break-words text-xs font-bold"></p><p id="support-info-meta" class="mt-0.5 break-words text-[11px] text-on-surface-variant"></p></div>
    <div id="support-chat-listview" class="min-h-0 flex-1 overflow-y-auto bg-surface p-2">
        <ul id="support-chat-list" class="space-y-1.5"></ul>
        <p id="support-chat-empty" class="hidden px-2 py-4 text-center text-xs text-on-surface-variant">You have no open requests.</p>
        <button type="button" id="support-chat-new" class="mt-3 flex w-full items-center justify-center gap-1 rounded-lg border border-primary/40 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/5"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>New request</button>
    </div>
    <ol id="support-chat-log" class="hidden min-h-0 flex-1 space-y-3 overflow-y-auto bg-surface p-3" aria-live="polite" aria-label="Conversation"></ol>
    <p id="support-chat-error" class="hidden shrink-0 break-words bg-error/5 px-3 py-1.5 text-xs text-error" role="alert"></p>
    <ul id="support-chat-files" class="hidden shrink-0 flex-wrap gap-1 border-t border-outline-variant/30 bg-white px-2 pt-2" aria-label="Files to send"></ul>
    <form id="support-chat-form" class="hidden shrink-0 items-end gap-2 border-t border-outline-variant/30 bg-white p-2">
        <label class="shrink-0 cursor-pointer self-center rounded-lg p-2 text-primary hover:bg-primary/10 focus-within:ring-2 focus-within:ring-primary" title="Attach photos or PDF (up to 3 files, 5 MB each)"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">attach_file</span><span class="sr-only">Attach files</span><input type="file" id="support-chat-attach" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" multiple></label>
        <label class="min-w-0 flex-1"><span class="sr-only">Your message</span><textarea id="support-chat-input" rows="2" maxlength="2000" placeholder="Type your message…" class="w-full resize-none rounded-lg border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary"></textarea></label>
        <button type="submit" class="shrink-0 rounded-lg bg-primary px-3 py-2.5 text-xs font-semibold text-white hover:bg-primary-container disabled:opacity-60" aria-label="Send message"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span></button>
    </form>
    <a href="{{ route('support') }}" class="shrink-0 border-t border-outline-variant/30 bg-white px-3 py-1.5 text-center text-[11px] font-semibold text-primary hover:underline">Open the full Support page (files and history)</a>
</section>
<script>
(() => {
    const fab = document.getElementById('support-fab');
    if (!fab) return;
    const $ = (id) => document.getElementById(id);
    const panel = $('support-chat'), log = $('support-chat-log'), form = $('support-chat-form'), input = $('support-chat-input');
    const listView = $('support-chat-listview'), list = $('support-chat-list'), info = $('support-chat-info'), back = $('support-chat-back');
    const badge = $('support-badge'), toast = $('support-toast'), status = $('support-chat-status'), err = $('support-chat-error');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const seenKey = 'support-seen-{{ auth()->id() }}', baseTitle = document.title;
    const store = { get: () => { try { return localStorage.getItem(seenKey); } catch (e) { return null; } }, set: (v) => { try { localStorage.setItem(seenKey, String(v)); } catch (e) {} } };
    const attach = $('support-chat-attach'), chips = $('support-chat-files');
    let picked = [];
    const OK_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    function showError(text) { err.textContent = text; err.classList.toggle('hidden', !text); }
    function renderChips() {
        chips.replaceChildren();
        picked.forEach((file, index) => {
            const chip = el('li', 'flex max-w-full items-center gap-1 rounded-full bg-surface px-2 py-0.5 text-[11px]');
            chip.appendChild(el('span', 'min-w-0 break-all', '📎 ' + file.name));
            const x = el('button', 'shrink-0 rounded-full px-1 font-bold text-error hover:bg-error/10', '×');
            x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + file.name);
            x.addEventListener('click', () => { picked.splice(index, 1); renderChips(); });
            chip.appendChild(x); chips.appendChild(chip);
        });
        show(chips, picked.length > 0, 'flex');
    }
    attach.addEventListener('change', () => {
        let problem = '';
        [...attach.files].forEach((file) => {
            if (picked.length >= 3) { problem = 'You can send up to 3 files at a time.'; }
            else if (!OK_TYPES.includes(file.type)) { problem = file.name + ': only photos (JPG, PNG, WebP) and PDF files are allowed.'; }
            else if (file.size > 5 * 1024 * 1024) { problem = file.name + ' is larger than 5 MB.'; }
            else { picked.push(file); }
        });
        attach.value = '';
        showError(problem); renderChips();
    });
    const readKey = 'support-read-{{ auth()->id() }}';
    const readMap = () => { try { return JSON.parse(localStorage.getItem(readKey) || '{}') || {}; } catch (e) { return {}; } };
    const markRead = (id, upTo) => { try { const m = readMap(); if ((m[id] || 0) < upTo) { m[id] = upTo; localStorage.setItem(readKey, JSON.stringify(m)); } } catch (e) {} };
    let state = null, view = 'list', selectedId = null, signature = '', timer = null, toastTimer = null;
    const isOpen = () => !panel.classList.contains('hidden');
    const staffId = () => (state && state.latest_staff ? state.latest_staff.id : 0);
    const el = (tag, cls, text) => { const n = document.createElement(tag); n.className = cls; if (text !== undefined) n.textContent = text; return n; };
    const show = (node, on, display) => { node.classList.toggle('hidden', !on); if (display) node.classList.toggle(display, on); };

    function renderList() {
        list.replaceChildren();
        const read = readMap();
        const isNew = (r) => r.latest_staff_id > (read[r.id] || 0);
        /* Requests with an unread reply first; each group keeps the server order (latest activity first). */
        [...state.requests].sort((x, y) => Number(isNew(y)) - Number(isNew(x))).forEach((r) => {
            const li = document.createElement('li');
            const updated = isNew(r);
            const btn = el('button', 'block w-full rounded-lg bg-white px-2.5 py-1.5 text-left shadow-sm ring-1 ring-outline-variant/30 hover:ring-primary/50' + (updated ? ' sc-updated' : ''));
            btn.type = 'button';
            const row = el('div', 'flex flex-wrap items-start justify-between gap-x-1');
            row.appendChild(el('p', 'min-w-0 flex-1 break-words text-[11px] font-bold leading-snug', r.subject));
            row.appendChild(el('span', 'shrink-0 rounded-full px-1.5 text-[9px] font-bold leading-4 ' + ({ 'On process': 'bg-secondary/10 text-secondary', 'Approved': 'bg-emerald-100 text-emerald-800', 'On hold': 'bg-slate-200 text-slate-700' }[r.status] || 'bg-amber-100 text-amber-800'), r.status));
            btn.appendChild(row);
            btn.appendChild(el('p', 'break-all text-[9px] leading-snug text-on-surface-variant', r.ticket + ' · ' + r.at));
            if (updated) btn.appendChild(el('p', 'text-[10px] font-bold leading-snug text-secondary', '● New reply'));
            btn.addEventListener('click', () => go('thread', r.id));
            li.appendChild(btn); list.appendChild(li);
        });
        show($('support-chat-empty'), state.requests.length === 0);
    }

    function renderThread() {
        const c = state.current;
        const items = c ? c.messages : [{ mine: false, body: 'Hello! How can we help you? Type your message below and Customer Services will answer here.', at: '' }];
        const sig = JSON.stringify([view, c && c.id, c && c.status, items.length]);
        if (sig === signature) return;
        signature = sig;
        const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 40;
        log.replaceChildren();
        items.forEach((m) => {
            const li = el('li', 'flex flex-col ' + (m.mine ? 'items-end' : 'items-start'));
            li.appendChild(el('div', 'max-w-[85%] whitespace-pre-line break-words rounded-2xl px-3 py-2 text-sm ' + (m.mine ? 'rounded-br-sm border border-primary/20 bg-primary/10' : 'rounded-bl-sm bg-white shadow-sm'), m.body));
            if (m.files && m.files.length) {
                const ul = el('ul', 'mt-0.5 max-w-[85%] space-y-0.5');
                m.files.forEach((fl) => { const row = document.createElement('li'); const a = el('a', 'inline-flex max-w-full items-center gap-1 break-all rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-primary shadow-sm ring-1 ring-outline-variant/30 hover:underline', '📎 ' + fl.name); a.href = fl.url; a.target = '_blank'; a.rel = 'noopener noreferrer'; row.appendChild(a); ul.appendChild(row); });
                li.appendChild(ul);
            }
            if (m.at) li.appendChild(el('p', 'mt-0.5 text-[10px] text-on-surface-variant', (m.mine ? 'You' : 'Customer Services') + ' · ' + m.at));
            log.appendChild(li);
        });
        if (c && !c.open) log.appendChild(el('li', 'text-center text-[11px] text-on-surface-variant', 'This request is resolved. Use New request if you still need help.'));
        if (atBottom || !isOpen()) log.scrollTop = log.scrollHeight;
    }

    function render() {
        if (!state) return;
        const thread = view !== 'list';
        show(listView, !thread); show(log, thread); show(back, thread);
        show(info, thread && !!state.current);
        const canWrite = thread && (view === 'new' || (state.current && state.current.open));
        show(form, canWrite, 'flex');
        if (!thread) { status.textContent = 'My open requests'; renderList(); signature = ''; return; }
        const c = state.current;
        if (c) {
            markRead(c.id, c.latest_staff_id);
            $('support-info-subject').textContent = c.subject;
            $('support-info-meta').textContent = [c.ticket, c.status, 'Filed ' + c.filed, c.handled_by ? 'Handled by ' + c.handled_by : ''].filter(Boolean).join(' · ');
            status.textContent = 'Request status: ' + c.status;
        } else { status.textContent = 'New request'; }
        renderThread();
    }

    function go(next, id) {
        view = next === 'thread' ? 'thread' : next; selectedId = id || null; signature = ''; err.classList.add('hidden'); picked = []; renderChips();
        if (state && next !== 'thread') state.current = null;
        render();
        load().then(() => { if (next !== 'list') { log.scrollTop = log.scrollHeight; input.focus(); } });
    }

    /** Lifts the chat above a banner fixed at the bottom of the screen (trial or Google Drive notice) so they never cover each other. */
    function clearBanners() {
        let lift = 0;
        document.querySelectorAll('.no-print[style*="position:fixed"], [role="alert"].fixed').forEach((n) => {
            const r = n.getBoundingClientRect();
            if (r.height > 0 && r.bottom > window.innerHeight - 8 && r.top > window.innerHeight / 2) lift = Math.max(lift, window.innerHeight - r.top);
        });
        document.documentElement.style.setProperty('--fab-extra', lift ? Math.ceil(lift) + 'px' : '0px');
    }

    function setBadge(count) {
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        fab.classList.toggle('sc-alert', count > 0);
        document.title = count ? '(' + count + ') ' + baseTitle : baseTitle;
    }
    function markSeen() { if (state) store.set(staffId()); setBadge(0); toast.classList.add('hidden'); }

    /** The icon shows only while a request is open (or a staff answer has not been read yet). */
    function updateVisibility() {
        const unseen = state && staffId() > Number(store.get() ?? staffId());
        const showIcon = isOpen() || (state && (state.requests.length > 0 || unseen));
        fab.classList.toggle('hidden', !showIcon);
        fab.classList.toggle('flex', !!showIcon);
    }

    async function load() {
        try {
            const res = await fetch(@json(route('support.widget')) + (view === 'thread' && selectedId ? '?id=' + selectedId : ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const first = state === null;
            state = await res.json();
            if (view !== 'thread') state.current = null;
            render();
            const seen = store.get();
            if (seen === null || isOpen()) { store.set(staffId()); updateVisibility(); return; }
            if (staffId() > Number(seen)) {
                setBadge(1);
                if (!first) {
                    $('support-toast-text').textContent = state.latest_staff.body;
                    toast.classList.remove('hidden');
                    clearTimeout(toastTimer);
                    toastTimer = setTimeout(() => toast.classList.add('hidden'), 8000);
                }
            }
            updateVisibility();
            clearBanners();
        } catch (e) { /* offline: try again on the next tick */ }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(async () => { if (!document.hidden) await load(); schedule(); }, isOpen() ? 5000 : 15000);
    }

    function open(requestId) {
        clearTimeout(closing); panel.classList.remove('sc-closing');
        panel.classList.remove('hidden'); panel.classList.add('flex');
        fab.setAttribute('aria-expanded', 'true');
        toast.classList.add('hidden');
        if (requestId) { view = 'thread'; selectedId = requestId; signature = ''; }
        load().then(() => { markSeen(); if (view !== 'list') log.scrollTop = log.scrollHeight; });
        schedule();
    }
    let closing = null;
    function close() {
        fab.setAttribute('aria-expanded', 'false'); fab.focus();
        panel.classList.add('sc-closing');
        clearTimeout(closing);
        closing = setTimeout(() => { panel.classList.add('hidden'); panel.classList.remove('flex', 'sc-closing'); updateVisibility(); schedule(); }, 150);
    }

    fab.addEventListener('click', () => (isOpen() ? close() : open()));
    $('support-chat-close').addEventListener('click', close);
    back.addEventListener('click', () => go('list'));
    $('support-chat-new').addEventListener('click', () => go('new'));
    toast.addEventListener('click', () => open(state && state.latest_staff ? state.latest_staff.request_id : null));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen()) close(); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const typed = input.value.trim();
        const body = typed || (picked.length ? 'Attached: ' + picked.map((file) => file.name).join(', ') : '');
        if (body.length < 3) { err.textContent = 'Please type at least 3 characters.'; err.classList.remove('hidden'); return; }
        err.classList.add('hidden');
        const btn = form.querySelector('button'); btn.disabled = true;
        const data = new FormData();
        data.append('body', body);
        picked.forEach((file) => data.append('files[]', file));
        const creating = view === 'new' || !(state && state.current);
        let url = @json(route('support.send'));
        if (creating) { data.append('type', 'general'); data.append('subject', body.length > 60 ? body.slice(0, 57) + '…' : body); } else { url = state.current.reply_url; }
        try {
            const res = await fetch(url, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin' });
            if (res.ok) {
                const j = await res.json().catch(() => ({}));
                input.value = ''; picked = []; renderChips();
                if (j.warning) showError(j.warning);
                if (creating && j.id) { view = 'thread'; selectedId = j.id; }
                signature = ''; await load(); markSeen(); log.scrollTop = log.scrollHeight;
            } else { const j = await res.json().catch(() => ({})); err.textContent = j.message || 'The message could not be sent. Please try again.'; err.classList.remove('hidden'); }
        } catch (x) { err.textContent = 'No connection. Please try again.'; err.classList.remove('hidden'); }
        btn.disabled = false; input.focus();
    });

    window.addEventListener('resize', clearBanners);
    document.addEventListener('DOMContentLoaded', () => setTimeout(clearBanners, 0));
    load(); schedule();
})();
</script>
@endunless
