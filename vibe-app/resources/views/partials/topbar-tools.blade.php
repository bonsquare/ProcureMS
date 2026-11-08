{{-- The clock (Philippine time, set by the server), the one notification bell and the profile icon, drawn as one capsule at the right end of the top bar of every app page. The profile icon of the page is moved into the capsule, so the three always stay together on one line. Drawn by a script because this partial sits in the page head; the bell that some pages had is replaced. --}}
@auth
<style>
    [data-topbar-tools] { display: flex; flex: none; flex-wrap: nowrap; align-items: center; gap: .125rem; margin-left: auto; padding: .25rem .3rem .25rem .95rem; border-radius: 2rem; white-space: nowrap; background: rgba(255, 255, 255, .66); -webkit-backdrop-filter: blur(14px) saturate(160%); backdrop-filter: blur(14px) saturate(160%); box-shadow: inset 0 0 0 1px rgba(16, 57, 103, .1), 0 1px 2px rgba(16, 57, 103, .06); }
    [data-topbar-parent] { flex-wrap: nowrap !important; min-width: 0; }
    [data-topbar-parent] > :not([data-topbar-tools]) { min-width: 0; }
    [data-topbar-tools] .tt-clock { text-align: right; padding-right: .6rem; font: 700 13px/1.1 Inter, system-ui, sans-serif; letter-spacing: -.01em; color: #14818f; font-variant-numeric: tabular-nums; }
    [data-topbar-tools] .tt-clock small { display: block; margin-top: 1px; font-weight: 500; font-size: 10.5px; letter-spacing: 0; color: #536273; }
    [data-topbar-tools] .tt-sep { align-self: center; width: 1px; height: 1.5rem; margin-right: .2rem; background: rgba(16, 57, 103, .14); }
    [data-topbar-tools] .tt-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; padding: 0; border-radius: 50%; border: 0; background: transparent; color: #3b4d61; cursor: pointer; transition: background-color 160ms ease, color 160ms ease, transform 140ms cubic-bezier(.2, .8, .2, 1); }
    [data-topbar-tools] .tt-bell:hover { background: #14818f; color: #fff; }
    [data-topbar-tools] .tt-bell:active { transform: scale(.94); }
    [data-topbar-tools] .tt-badge { position: absolute; top: 1px; right: 1px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: #ba1a1a; color: #fff; font: 700 10px/16px Inter, system-ui, sans-serif; text-align: center; box-shadow: 0 0 0 2px #fff; }
    [data-topbar-tools] .tt-panel { position: absolute; right: -2.9rem; top: calc(100% + .6rem); z-index: 120; width: min(22rem, calc(100vw - 1.5rem)); max-height: 70vh; overflow-y: auto; border-radius: 1rem; border: 1px solid rgba(16, 57, 103, .12); background: rgba(255, 255, 255, .96); -webkit-backdrop-filter: blur(18px); backdrop-filter: blur(18px); box-shadow: 0 24px 56px -14px rgba(16, 57, 103, .45); transform-origin: top right; animation: tt-pop 220ms cubic-bezier(.2, .9, .25, 1.04); }
    [data-topbar-tools] .tt-panel[hidden] { display: none; }
    @keyframes tt-pop { from { opacity: 0; transform: translateY(-6px) scale(.97); } to { opacity: 1; transform: none; } }
    [data-topbar-tools] .tt-head { display: flex; align-items: center; gap: .5rem; padding: .65rem .6rem .65rem 1rem; border-bottom: 1px solid #e3eaf1; }
    [data-topbar-tools] .tt-head h3 { margin: 0; flex: 1; min-width: 0; padding: 0; border: 0; font: 700 13px Inter, system-ui, sans-serif; letter-spacing: -.01em; }
    [data-topbar-tools] .tt-count { min-width: 20px; padding: 0 .4rem; border-radius: 10px; background: #14818f; color: #fff; font: 700 11px/20px Inter, system-ui, sans-serif; text-align: center; }
    [data-topbar-tools] .tt-clearall { flex: none; padding: .3rem .55rem; border: 0; border-radius: .5rem; background: transparent; color: #14818f; font: 600 12px Inter, system-ui, sans-serif; cursor: pointer; }
    [data-topbar-tools] .tt-clearall:hover { background: #e3f1f3; }
    [data-topbar-tools] .tt-item { display: flex; align-items: flex-start; gap: .65rem; padding: .7rem .5rem .7rem .85rem; border-bottom: 1px solid #eef2f6; transition: background-color 140ms ease, opacity 160ms ease, transform 160ms ease; }
    [data-topbar-tools] .tt-item:hover { background: #f1f6fb; }
    [data-topbar-tools] .tt-item.tt-leaving { opacity: 0; transform: translateX(16px); }
    [data-topbar-tools] .tt-ico { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: #e0f2f5; color: #14818f; }
    [data-topbar-tools] .tt-ico .material-symbols-outlined { font-size: 18px; }
    [data-topbar-tools] .tt-item[data-level="warn"] .tt-ico { background: #fdf0d9; color: #b45309; }
    [data-topbar-tools] .tt-item[data-level="error"] .tt-ico { background: #fbe4e4; color: #ba1a1a; }
    [data-topbar-tools] .tt-main { display: block; flex: 1; min-width: 0; text-decoration: none; color: #1b2a3a; }
    [data-topbar-tools] .tt-main strong { display: block; font: 600 13px/1.3 Inter, system-ui, sans-serif; overflow-wrap: anywhere; }
    [data-topbar-tools] .tt-main span { display: block; margin-top: 2px; font: 400 12px/1.35 Inter, system-ui, sans-serif; color: #536273; overflow-wrap: anywhere; }
    [data-topbar-tools] .tt-x { flex: none; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border: 0; border-radius: 50%; background: transparent; color: #7a8794; cursor: pointer; }
    [data-topbar-tools] .tt-x:hover { background: #e8eef4; color: #ba1a1a; }
    [data-topbar-tools] .tt-x .material-symbols-outlined { font-size: 16px; }
    [data-topbar-tools] .tt-clearall:focus-visible, [data-topbar-tools] .tt-x:focus-visible, [data-topbar-tools] .tt-main:focus-visible { outline: 2px solid #14818f; outline-offset: 1px; }
    [data-topbar-tools] .tt-item[data-level="warn"] { border-left: 3px solid #d97706; }
    [data-topbar-tools] .tt-item[data-level="error"] { border-left: 3px solid #ba1a1a; }
    [data-topbar-tools] .tt-item[data-level="info"] { border-left: 3px solid #1a98a8; }
    [data-topbar-tools] .tt-empty { display: grid; justify-items: center; gap: .25rem; padding: 1.5rem 1rem; text-align: center; font: 400 13px Inter, system-ui, sans-serif; color: #536273; }
    [data-topbar-tools] .tt-empty .material-symbols-outlined { font-size: 34px; color: #2aa86b; }
    [data-topbar-tools] .tt-empty p { margin: 0; }
    /* The profile icon inside the capsule keeps its own size and its menu opens under it. */
    [data-topbar-tools] > :last-child { flex: none; }
    @media (max-width: 767px) {
        [data-topbar-tools] { padding-left: .7rem; }
        [data-topbar-tools] .tt-clock small { display: none; }
        [data-topbar-tools] .tt-clock { font-size: 12px; padding-right: .4rem; }
    }
    @media (max-width: 639px) {
        [data-topbar-tools] .tt-panel { position: fixed; top: 4.25rem; left: .75rem; right: .75rem; width: auto; }
    }
    @media (max-width: 380px) { [data-topbar-tools] .tt-sep { display: none; } [data-topbar-tools] .tt-clock { padding-right: .25rem; } }
    @media (prefers-reduced-motion: reduce) { [data-topbar-tools] .tt-panel { animation: none; } [data-topbar-tools] .tt-item { transition: none; } }
    @media (prefers-reduced-transparency: reduce) { [data-topbar-tools], [data-topbar-tools] .tt-panel { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; } }
    @media print { [data-topbar-tools] { display: none !important; } }
</style>
<script data-topbar-tools-script>
    (() => {
        const clockUrl = @json(route('server-time'));
        const feedUrl = @json(route('notifications'));
        const zone = 'Asia/Manila';
        let offset = 0;

        const place = () => {
            if (document.querySelector('[data\\2d official-page]')) return null; // an official document has a letterhead, not a top bar; the name is written with a CSS escape so that no page that only carries this script looks like an official page to the drive banner
            const header = document.querySelector('header');
            if (!header || header.querySelector('[data-topbar-tools]')) return null;
            // The old per-page bell goes: this one is the same on every page.
            header.querySelectorAll('button[aria-label="Notifications"]').forEach((old) => old.remove());
            const profile = header.querySelector('#open-user-menu')?.parentElement || header.querySelector('.h-8.w-8.rounded-full.bg-primary') || header.querySelector('.rounded-full.bg-primary');
            const host = document.createElement('div');
            host.setAttribute('data-topbar-tools', '');
            const parent = profile?.parentElement && profile.parentElement !== header ? profile.parentElement : (profile ? header : (header.lastElementChild || header));
            if (profile && profile.parentElement) profile.parentElement.insertBefore(host, profile);
            else parent.append(host);
            if (parent !== header) parent.setAttribute('data-topbar-parent', '');
            host.profile = profile;
            return host;
        };

        const build = (host) => {
            const clock = document.createElement('div');
            clock.className = 'tt-clock'; clock.setAttribute('aria-label', 'Date and time in the Philippines');
            const time = document.createElement('time'); const date = document.createElement('small');
            clock.append(time, date);
            const sep = document.createElement('span'); sep.className = 'tt-sep'; sep.setAttribute('aria-hidden', 'true');

            const wrap = document.createElement('div'); wrap.style.position = 'relative';
            const bell = document.createElement('button');
            bell.type = 'button'; bell.className = 'tt-bell'; bell.setAttribute('aria-label', 'Notifications'); bell.setAttribute('aria-expanded', 'false');
            const icon = document.createElement('span'); icon.className = 'material-symbols-outlined'; icon.setAttribute('aria-hidden', 'true'); icon.style.fontSize = '22px'; icon.textContent = 'notifications';
            const badge = document.createElement('span'); badge.className = 'tt-badge'; badge.hidden = true;
            bell.append(icon, badge);
            const panel = document.createElement('div'); panel.className = 'tt-panel'; panel.hidden = true; panel.setAttribute('role', 'region'); panel.setAttribute('aria-label', 'Notifications');
            wrap.append(bell, panel);
            host.append(clock, sep, wrap);
            // The profile icon joins the capsule: clock, bell and profile are one group that never splits over two lines.
            if (host.profile) host.append(host.profile);

            const now = () => new Date(Date.now() + offset);
            const tick = () => {
                time.textContent = now().toLocaleTimeString('en-PH', { timeZone: zone, hour: 'numeric', minute: '2-digit', second: '2-digit' });
                date.textContent = now().toLocaleDateString('en-PH', { timeZone: zone, weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            };
            const sync = async () => {
                try { const response = await fetch(clockUrl, { headers: { Accept: 'application/json' } }); if (response.ok) offset = Date.parse((await response.json()).now) - Date.now(); } catch (error) { /* the computer clock is used until the next try */ }
                tick();
            };

            const clearedKey = 'bell-cleared-' + @json(auth()->id());
            const readCleared = () => { try { return new Set(JSON.parse(localStorage.getItem(clearedKey) || '[]')); } catch (error) { return new Set(); } };
            const writeCleared = (set) => { try { localStorage.setItem(clearedKey, JSON.stringify([...set].slice(-200))); } catch (error) { /* clearing then lasts until the page is reloaded */ } };
            const keyOf = (entry) => entry.key || [entry.level, entry.title, entry.detail, entry.url].join('|');
            const fallbackIcon = { error: 'error', warn: 'warning', info: 'info' };
            let lastData = { count: 0, items: [] };
            const el = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };
            const glyph = (name) => { const node = el('span', 'material-symbols-outlined', name); node.setAttribute('aria-hidden', 'true'); return node; };

            const render = (data) => {
                lastData = data;
                // A cleared notification stays hidden until it changes (a newer reply, another count); keys of notifications that are gone are forgotten.
                const cleared = readCleared();
                const present = new Set(data.items.map(keyOf));
                const kept = new Set([...cleared].filter((key) => present.has(key)));
                if (kept.size !== cleared.size) writeCleared(kept);
                const items = data.items.filter((entry) => !kept.has(keyOf(entry)));

                badge.hidden = items.length === 0; badge.textContent = items.length > 9 ? '9+' : items.length;
                bell.setAttribute('aria-label', 'Notifications' + (items.length ? ', ' + items.length + ' new' : ''));
                panel.replaceChildren();

                const head = el('div', 'tt-head');
                head.append(el('h3', '', 'Notifications'));
                if (items.length) {
                    head.append(el('span', 'tt-count', String(items.length)));
                    const all = el('button', 'tt-clearall', 'Clear all'); all.type = 'button';
                    all.addEventListener('click', (event) => { event.stopPropagation(); const next = readCleared(); items.forEach((entry) => next.add(keyOf(entry))); writeCleared(next); render(lastData); });
                    head.append(all);
                }
                panel.append(head);

                if (!items.length) {
                    const none = el('div', 'tt-empty'); none.append(glyph('check_circle'), el('p', '', 'You are all caught up.'));
                    panel.append(none); return;
                }
                items.forEach((entry) => {
                    const row = el('div', 'tt-item'); row.dataset.level = entry.level;
                    const icon = el('span', 'tt-ico'); icon.append(glyph(entry.icon || fallbackIcon[entry.level] || 'notifications'));
                    const main = el('a', 'tt-main'); main.href = entry.url; main.append(el('strong', '', entry.title), el('span', '', entry.detail));
                    const clear = el('button', 'tt-x'); clear.type = 'button'; clear.title = 'Clear this notification'; clear.setAttribute('aria-label', 'Clear notification: ' + entry.title); clear.append(glyph('close'));
                    clear.addEventListener('click', (event) => {
                        event.stopPropagation();
                        const next = readCleared(); next.add(keyOf(entry)); writeCleared(next);
                        row.classList.add('tt-leaving');
                        setTimeout(() => render(lastData), 170);
                    });
                    row.append(icon, main, clear); panel.append(row);
                });
            };
            const load = async () => {
                if (document.hidden) return;
                try { const response = await fetch(feedUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); if (response.ok) render(await response.json()); } catch (error) { /* the bell keeps what it had */ }
            };

            bell.addEventListener('click', (event) => { event.stopPropagation(); panel.hidden = !panel.hidden; bell.setAttribute('aria-expanded', String(!panel.hidden)); if (!panel.hidden) load(); });
            document.addEventListener('click', (event) => { if (!wrap.contains(event.target)) { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); } });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); } });
            tick(); sync(); load();
            setInterval(tick, 1000);
            setInterval(sync, 300000);
            setInterval(load, 60000);
        };

        document.addEventListener('DOMContentLoaded', () => { const host = place(); if (host) build(host); });
    })();
</script>
@endauth
