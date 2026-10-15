{{-- A docked preview of the official form beside a planning page (data entry layer only; the official form is not touched).
     $key: remembers open or closed · $title: name of the form · $savedUrl: the print page (GET) · $drafts: CSS selector of the forms whose entry is drawn before it is saved.
     A draft form posts to its own action + "/preview", which makes the entry inside a transaction that is rolled back. --}}
<style>
    #doc-dock{position:fixed;top:4rem;right:0;bottom:0;z-index:30;display:none;width:var(--dock-w,46vw);max-width:100vw;flex-direction:column;background:#d9dee5;border-left:1px solid #cbd7e1;box-shadow:-8px 0 24px rgba(16,57,103,.12)}
    #doc-dock-bar{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:6px;padding:8px 12px;background:#fff;border-bottom:1px solid #cbd7e1;font-size:12px;font-weight:700;color:#103967}
    #doc-dock-bar small{font-weight:400;color:#536273}
    #doc-dock-frame{width:100%;flex:1;border:0;background:#d9dee5}
    body.dock-open #doc-dock{display:flex}
    body.dock-open #doc-dock-open{display:none}
    @media(min-width:1100px){:root{--dock-w:min(46vw,760px)}body.dock-open main{padding-right:calc(var(--dock-w) + 1rem)!important}}
    @media(max-width:1099px){#doc-dock{width:100vw}}
</style>
<button type="button" id="doc-dock-open" class="fixed bottom-4 right-4 z-30 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2.5 text-xs font-semibold text-white shadow-lg hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">description</span>Show the official form</button>
<aside id="doc-dock" aria-label="{{ $title }} preview">
    <div id="doc-dock-bar"><span>{{ $title }} <small>(updates as you edit)</small></span>
        <span class="flex items-center gap-2"><small id="doc-dock-status">Ready</small>
            <select id="doc-dock-zoom" aria-label="Preview zoom" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option></select>
            <button type="button" id="doc-dock-refresh" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold hover:bg-surface-low">Refresh</button>
            <button type="button" id="doc-dock-close" class="rounded border border-outline-variant/60 bg-white px-2 py-1 text-xs font-semibold hover:bg-surface-low">Hide</button></span></div>
    <iframe id="doc-dock-frame" title="{{ $title }}"></iframe>
</aside>
<script>
    (() => {
        const key = @json('dock-'.$key);
        const savedUrl = @json($savedUrl);
        const draftSelector = @json($drafts);
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || @json(csrf_token());
        const dock = document.getElementById('doc-dock');
        // The preview and its button are fixed on the screen; they are kept outside the page content so scaling the page never scales them.
        document.body.append(dock, document.getElementById('doc-dock-open'));
        const frame = document.getElementById('doc-dock-frame');
        const status = document.getElementById('doc-dock-status');
        const zoomSelect = document.getElementById('doc-dock-zoom');
        const hideChrome = '<style>.official-toolbar{display:none!important}body{background:#d9dee5!important}.official-sheet{zoom:var(--pz,1)!important;margin-top:12px!important;margin-bottom:12px!important}</style>';
        let timer = null; let controller = null; let lastDraft = null;
        const isOpen = () => document.body.classList.contains('dock-open');
        // With the preview beside it the page has less room: its text and boxes are scaled down to fit (the whole page, so every screen of the system follows),
        // never below 60% (the smallest text, 10px, is then 6px). Closing the preview puts the page back to its normal size.
        const main = document.querySelector('main');
        const scalePage = () => {
            if (!main) return;
            // the page itself is scaled; the preview and its button (also inside main) keep their size
            const content = [...main.children].filter((element) => !element.matches('#doc-dock, #doc-dock-open, script, style'));
            content.forEach((element) => element.style.removeProperty('zoom'));
            if (!isOpen() || window.innerWidth < 1100) { return; }
            const room = window.innerWidth - dock.getBoundingClientRect().width - main.getBoundingClientRect().left - 32;
            const scale = Math.min(1, Math.max(0.6, room / 1000));
            content.forEach((element) => element.style.setProperty('zoom', scale.toFixed(3)));
        };
        window.addEventListener('resize', scalePage);
        const setOpen = (open) => { document.body.classList.toggle('dock-open', open); try { sessionStorage.setItem(key, open ? '1' : '0'); } catch (error) { /* storage can be blocked */ } scalePage(); if (open) { render(lastDraft); } };
        const applyZoom = () => {
            const doc = frame.contentDocument;
            const sheet = doc && doc.querySelector('.official-sheet, [data-official-page]');
            if (!sheet) return;
            let zoom = parseFloat(zoomSelect.value);
            if (zoomSelect.value === 'fit') {
                doc.documentElement.style.setProperty('--pz', 1);
                const box = sheet.getBoundingClientRect();
                zoom = Math.min(0.9, Math.max(0.3, (frame.clientWidth - 48) / (Math.max(sheet.scrollWidth, box.width) || 1)));
            }
            doc.documentElement.style.setProperty('--pz', zoom);
        };
        frame.addEventListener('load', () => setTimeout(applyZoom, 350));
        zoomSelect.addEventListener('change', applyZoom);
        const show = (html) => { frame.srcdoc = /<head[^>]*>/i.test(html) ? html.replace(/(<head[^>]*>)/i, '$1' + hideChrome) : hideChrome + html; };
        const render = async (form) => {
            if (!isOpen()) return;
            if (controller) controller.abort();
            controller = new AbortController();
            status.textContent = 'Updating...';
            try {
                let response;
                if (form) {
                    const body = new FormData(form);
                    if (!body.has('_token')) body.append('_token', csrf);
                    body.delete('_method'); // the preview address takes a plain POST
                    response = await fetch(form.action.replace(/\/$/, '') + '/preview', { method: 'POST', body, signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                } else {
                    response = await fetch(savedUrl, { signal: controller.signal, headers: { 'Accept': 'text/html' } });
                }
                if (!response.ok) { status.textContent = 'Preview unavailable (HTTP ' + response.status + ')'; return; }
                show(await response.text());
                status.textContent = form ? 'Showing your entry (not saved yet)' : 'Saved form';
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Preview unavailable. Click Refresh';
            }
        };
        const schedule = (form) => { lastDraft = form; clearTimeout(timer); timer = setTimeout(() => render(form), 900); };
        document.addEventListener('input', (event) => { const form = event.target.closest && event.target.closest(draftSelector); if (form && !form.querySelector('input[name=_method][value=DELETE]')) schedule(form); });
        document.addEventListener('change', (event) => { const form = event.target.closest && event.target.closest(draftSelector); if (form && !form.querySelector('input[name=_method][value=DELETE]')) schedule(form); });
        document.getElementById('doc-dock-open').addEventListener('click', () => setOpen(true));
        document.getElementById('doc-dock-close').addEventListener('click', () => setOpen(false));
        document.getElementById('doc-dock-refresh').addEventListener('click', () => { lastDraft = null; render(null); });
        let remembered = false;
        try { remembered = sessionStorage.getItem(key) === '1'; } catch (error) { /* storage can be blocked */ }
        if (remembered && window.innerWidth >= 1100) setOpen(true); // on a phone the preview covers the page, so it opens only on request
    })();
</script>
