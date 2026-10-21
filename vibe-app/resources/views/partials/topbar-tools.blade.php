{{-- The clock (Philippine time, set by the server) and the one notification bell, before the profile icon of every app page. Drawn by a script because this partial sits in the page head; the bell that some pages had is replaced. --}}
@auth
<style>
    [data-topbar-tools] { display: flex; align-items: center; gap: .5rem; margin-right: .5rem; }
    [data-topbar-tools] .tt-clock { text-align: right; font: 600 12px/1.15 Inter, system-ui, sans-serif; color: #1b2a3a; white-space: nowrap; font-variant-numeric: tabular-nums; }
    [data-topbar-tools] .tt-clock small { display: block; font-weight: 500; font-size: 10.5px; color: #536273; }
    [data-topbar-tools] .tt-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 50%; border: 0; background: transparent; color: #536273; cursor: pointer; }
    [data-topbar-tools] .tt-bell:hover { background: #103967; color: #fff; }
    [data-topbar-tools] .tt-badge { position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: #ba1a1a; color: #fff; font: 700 10px/16px Inter, system-ui, sans-serif; text-align: center; }
    [data-topbar-tools] .tt-panel { position: absolute; right: 0; top: calc(100% + .5rem); z-index: 120; width: min(22rem, calc(100vw - 1.5rem)); max-height: 70vh; overflow-y: auto; border-radius: .75rem; border: 1px solid rgba(16, 57, 103, .15); background: #fff; box-shadow: 0 20px 50px -12px rgba(16, 57, 103, .4); }
    [data-topbar-tools] .tt-panel h3 { margin: 0; padding: .75rem 1rem; font: 700 13px Inter, system-ui, sans-serif; border-bottom: 1px solid #e3eaf1; }
    [data-topbar-tools] .tt-item { display: block; padding: .7rem 1rem; text-decoration: none; color: #1b2a3a; border-bottom: 1px solid #eef2f6; }
    [data-topbar-tools] .tt-item:hover { background: #f1f6fb; }
    [data-topbar-tools] .tt-item strong { display: block; font: 600 13px/1.3 Inter, system-ui, sans-serif; }
    [data-topbar-tools] .tt-item span { display: block; margin-top: 2px; font: 400 12px/1.35 Inter, system-ui, sans-serif; color: #536273; }
    [data-topbar-tools] .tt-item[data-level="warn"] { border-left: 3px solid #d97706; }
    [data-topbar-tools] .tt-item[data-level="error"] { border-left: 3px solid #ba1a1a; }
    [data-topbar-tools] .tt-item[data-level="info"] { border-left: 3px solid #174b82; }
    [data-topbar-tools] .tt-empty { padding: 1.25rem 1rem; text-align: center; font: 400 13px Inter, system-ui, sans-serif; color: #536273; }
    @media (max-width: 767px) { [data-topbar-tools] .tt-clock small { display: none; } }
    @media print { [data-topbar-tools] { display: none !important; } }
</style>
<script data-topbar-tools-script>
    (() => {
        const clockUrl = @json(route('server-time'));
        const feedUrl = @json(route('notifications'));
        const zone = 'Asia/Manila';
        let offset = 0;

        const place = () => {
            const header = document.querySelector('header');
            if (!header || header.querySelector('[data-topbar-tools]')) return null;
            // The old per-page bell goes: this one is the same on every page.
            header.querySelectorAll('button[aria-label="Notifications"]').forEach((old) => old.remove());
            const profile = header.querySelector('#open-user-menu')?.parentElement || header.querySelector('.h-8.w-8.rounded-full.bg-primary') || header.querySelector('.rounded-full.bg-primary');
            const host = document.createElement('div');
            host.setAttribute('data-topbar-tools', '');
            if (profile && profile.parentElement) profile.parentElement.insertBefore(host, profile);
            else (header.lastElementChild || header).append(host);
            return host;
        };

        const build = (host) => {
            const clock = document.createElement('div');
            clock.className = 'tt-clock'; clock.setAttribute('aria-label', 'Date and time in the Philippines');
            const time = document.createElement('time'); const date = document.createElement('small');
            clock.append(time, date);

            const wrap = document.createElement('div'); wrap.style.position = 'relative';
            const bell = document.createElement('button');
            bell.type = 'button'; bell.className = 'tt-bell'; bell.setAttribute('aria-label', 'Notifications'); bell.setAttribute('aria-expanded', 'false');
            const icon = document.createElement('span'); icon.className = 'material-symbols-outlined'; icon.setAttribute('aria-hidden', 'true'); icon.textContent = 'notifications';
            const badge = document.createElement('span'); badge.className = 'tt-badge'; badge.hidden = true;
            bell.append(icon, badge);
            const panel = document.createElement('div'); panel.className = 'tt-panel'; panel.hidden = true; panel.setAttribute('role', 'region'); panel.setAttribute('aria-label', 'Notifications');
            wrap.append(bell, panel);
            host.append(clock, wrap);

            const now = () => new Date(Date.now() + offset);
            const tick = () => {
                time.textContent = now().toLocaleTimeString('en-PH', { timeZone: zone, hour: 'numeric', minute: '2-digit', second: '2-digit' });
                date.textContent = now().toLocaleDateString('en-PH', { timeZone: zone, weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            };
            const sync = async () => {
                try { const response = await fetch(clockUrl, { headers: { Accept: 'application/json' } }); if (response.ok) offset = Date.parse((await response.json()).now) - Date.now(); } catch (error) { /* the computer clock is used until the next try */ }
                tick();
            };

            const render = (data) => {
                badge.hidden = data.count === 0; badge.textContent = data.count > 9 ? '9+' : data.count;
                bell.setAttribute('aria-label', 'Notifications' + (data.count ? ', ' + data.count + ' new' : ''));
                panel.replaceChildren();
                const title = document.createElement('h3'); title.textContent = 'Notifications'; panel.append(title);
                if (!data.items.length) { const none = document.createElement('p'); none.className = 'tt-empty'; none.textContent = 'You are all caught up.'; panel.append(none); return; }
                data.items.forEach((entry) => {
                    const link = document.createElement('a'); link.className = 'tt-item'; link.href = entry.url; link.dataset.level = entry.level;
                    const head = document.createElement('strong'); head.textContent = entry.title; const detail = document.createElement('span'); detail.textContent = entry.detail;
                    link.append(head, detail); panel.append(link);
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
