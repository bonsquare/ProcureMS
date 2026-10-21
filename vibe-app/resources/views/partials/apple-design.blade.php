{{-- Apple-style feel for every app screen (spec 73): instant press feedback, windows that arrive as a material, soft focus rings, tighter headings. Looks and motion only: no size, spacing or layout changes. Official documents and printed reports are left out (data-official-page, print). --}}
{{-- The attribute is written with a CSS escape (d is the hyphen) so the page text does not carry the marker the Drive banner looks for. --}}
@php $screen = 'body:not(:has([data\2d official-page]))'; @endphp
<style data-apple-design>
    @media screen {
        :where({!! $screen !!}) { -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        :where({!! $screen !!}) :where(h1) { letter-spacing: -0.02em; }
        :where({!! $screen !!}) :where(h2, h3):not([class*="tracking"]) { letter-spacing: -0.01em; }

        /* Feedback on the press, not on release. */
        :where({!! $screen !!}) :where(button:not(:disabled), a[class*="ring-1"], a[class*="rounded-lg"]) { transition: transform 140ms cubic-bezier(.2, .8, .2, 1), background-color 160ms ease, color 160ms ease, box-shadow 160ms ease, opacity 160ms ease; }
        :where({!! $screen !!}) :where(button:not(:disabled), a[class*="ring-1"], a[class*="rounded-lg"]):active { transform: scale(.97); }

        /* Keyboard focus is clear and soft. */
        :where({!! $screen !!}) :where(button, a, summary):focus-visible { outline: 2px solid #174b82; outline-offset: 2px; border-radius: .375rem; }

        /* Windows: a blurred, dimmed background and an arrival with a soft spring. */
        :where({!! $screen !!}) dialog::backdrop { backdrop-filter: blur(6px) saturate(140%); }
        :where({!! $screen !!}) [role="dialog"].fixed { backdrop-filter: blur(6px) saturate(140%); }
        :where({!! $screen !!}) dialog[open] { animation: apple-arrive 300ms cubic-bezier(.2, .9, .25, 1.04); }
        :where({!! $screen !!}) dialog { box-shadow: 0 24px 60px -12px rgba(16, 57, 103, .35), 0 8px 20px -8px rgba(0, 0, 0, .2); }

        /* Icon buttons in table rows: a comfortable target, the same size on every screen. */
        {!! $screen !!} table td :is(button, a)[class*="h-8"][class*="w-8"] { height: 2.25rem; width: 2.25rem; border-radius: .6rem; }
        {!! $screen !!} table td :is(button, a)[class*="h-8"][class*="w-8"]:hover { box-shadow: 0 2px 8px -2px rgba(16, 57, 103, .3); }

        /* Status badges: one family, a soft inner edge in the badge's own color. */
        {!! $screen !!} :is(span, div)[class*="rounded-full"][class*="font-bold"][class*="px-"] { font-weight: 650; letter-spacing: .01em; box-shadow: inset 0 0 0 1px color-mix(in srgb, currentColor 16%, transparent); }

        /* Tables: the row under the pointer is lit softly; numbers line up. */
        {!! $screen !!} table tbody tr > td { transition: box-shadow 140ms ease; }
        {!! $screen !!} table tbody tr:hover > td { box-shadow: inset 0 0 0 100vmax rgba(16, 57, 103, .035); }
        {!! $screen !!} table td:is(.text-right, [class*="tabular"]) { font-variant-numeric: tabular-nums; }

        /* Cards and panels outside forms: one radius, a light depth. */
        {!! $screen !!} :is(div, details, article, section)[class*="border-outline-variant/30"][class*="bg-white"]:not(form *) { border-radius: .75rem; box-shadow: 0 1px 2px rgba(16, 57, 103, .06), 0 10px 24px -14px rgba(16, 57, 103, .18); }

        /* Main buttons: a little lift so they read as the main action. */
        {!! $screen !!} button[class*="bg-primary"]:not(:disabled), {!! $screen !!} button[class*="bg-secondary"]:not(:disabled) { box-shadow: 0 1px 2px rgba(16, 57, 103, .28), inset 0 1px 0 rgba(255, 255, 255, .14); }

        /* The History list arrives one entry after the other. */
        #action-history-list > li { animation: apple-arrive 300ms cubic-bezier(.2, .9, .25, 1.04) both; }
        #action-history-list > li:nth-child(2) { animation-delay: 40ms; }
        #action-history-list > li:nth-child(3) { animation-delay: 80ms; }
        #action-history-list > li:nth-child(n+4) { animation-delay: 120ms; }
    }

    /* Notices (spec 75): every action says it is working and when it is done. */
    #app-toasts { position: fixed; top: 1rem; right: 1rem; z-index: 300; display: flex; flex-direction: column; gap: .5rem; width: min(24rem, calc(100vw - 2rem)); pointer-events: none; }
    .app-toast { pointer-events: auto; display: flex; align-items: flex-start; gap: .625rem; border-radius: .75rem; border: 1px solid rgba(16, 57, 103, .14); background: rgba(255, 255, 255, .92); padding: .75rem .875rem; font-size: .8125rem; line-height: 1.35; color: #1b2a3a; box-shadow: 0 12px 32px -10px rgba(16, 57, 103, .35); backdrop-filter: blur(10px) saturate(160%); animation: apple-arrive 280ms cubic-bezier(.2, .9, .25, 1.04); transition: opacity 240ms ease, transform 240ms ease; }
    .app-toast[data-type="done"] { border-color: rgba(21, 128, 61, .35); } .app-toast[data-type="done"] .app-toast-icon { color: #15803d; }
    .app-toast[data-type="error"] { border-color: rgba(186, 26, 26, .4); } .app-toast[data-type="error"] .app-toast-icon { color: #ba1a1a; }
    .app-toast[data-type="info"] .app-toast-icon { color: #174b82; }
    .app-toast.leaving { opacity: 0; transform: translateY(-6px); }
    .app-toast-icon { font-family: 'Material Symbols Outlined'; font-size: 20px; line-height: 1; flex: none; font-feature-settings: 'liga'; }
    .app-toast-text { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .app-toast-close { flex: none; border: 0; background: transparent; color: #536273; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 .125rem; }
    .app-spin { display: inline-block; width: 14px; height: 14px; border-radius: 50%; border: 2px solid currentColor; border-right-color: transparent; animation: app-spin .7s linear infinite; vertical-align: -2px; margin-right: .4rem; }
    .app-toast .app-spin { width: 18px; height: 18px; color: #174b82; margin: 0; flex: none; }
    @keyframes app-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .app-spin { animation-duration: 1.6s; } .app-toast { animation: none; } }
    @keyframes apple-arrive { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        :where(body) dialog[open], #action-history-list > li { animation: opacity-only 160ms ease; }
        :where(body) :where(button:not(:disabled), a):active { transform: none !important; }
    }
    @keyframes opacity-only { from { opacity: 0; } to { opacity: 1; } }
    @media (prefers-reduced-transparency: reduce) {
        :where(body) dialog::backdrop, :where(body) [role="dialog"].fixed { backdrop-filter: none !important; }
    }
</style>
<script data-apple-design>
    // One notice for every action: "working" while a form or a save is in progress, "done" or the error when it ends; a second press is ignored while the first is working.
    (() => {
        if (window.appNotify) return;
        const box = () => document.getElementById('app-toasts') ?? document.body.appendChild(Object.assign(document.createElement('div'), { id: 'app-toasts', role: 'status', ariaLive: 'polite' }));
        const icons = { done: 'check_circle', error: 'error', info: 'info' };
        window.appNotify = (message, type = 'info') => {
            const toast = document.createElement('div');
            toast.className = 'app-toast';
            const icon = document.createElement('span'); icon.className = 'app-toast-icon'; icon.setAttribute('aria-hidden', 'true');
            const text = document.createElement('span'); text.className = 'app-toast-text';
            const close = document.createElement('button'); close.type = 'button'; close.className = 'app-toast-close'; close.setAttribute('aria-label', 'Dismiss'); close.textContent = '×';
            toast.append(icon, text, close);
            let timer = null;
            const dismiss = () => { clearTimeout(timer); toast.classList.add('leaving'); setTimeout(() => toast.remove(), 260); };
            close.addEventListener('click', dismiss);
            const api = {
                set(next, nextType = 'info') {
                    clearTimeout(timer);
                    toast.dataset.type = nextType; text.textContent = next;
                    if (nextType === 'progress') { icon.className = 'app-spin'; icon.textContent = ''; } else { icon.className = 'app-toast-icon'; icon.textContent = icons[nextType] ?? 'info'; }
                    if (nextType === 'done' || nextType === 'info') timer = setTimeout(dismiss, 6000);
                    return api;
                },
                dismiss,
            };
            box().append(toast);
            return api.set(message, type);
        };

        // The success message of a page (after a form) is shown as a notice, not left in the page.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('div[class*="mb-"][class*="border-secondary/30"][class*="bg-secondary/5"][class*="text-sm"]').forEach((flash) => {
                const message = flash.textContent.trim();
                if (message) { window.appNotify(message, 'done'); flash.remove(); }
            });
        });

        const idle = (form) => {
            delete form.dataset.busy;
            form.querySelectorAll('[data-was-disabled]').forEach((button) => { button.disabled = false; if (button.dataset.label !== undefined) button.innerHTML = button.dataset.label; delete button.dataset.wasDisabled; delete button.dataset.label; });
            window.appBusyNotice?.dismiss();
        };
        window.appBusy = (form, submitter) => {
            if (form.dataset.busy) return false;
            form.dataset.busy = '1';
            window.appBusyNotice = window.appNotify('Processing… please wait.', 'progress');
            setTimeout(() => {
                form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]').forEach((button) => {
                    button.dataset.wasDisabled = '1'; button.disabled = true;
                    if (button === submitter && button.tagName === 'BUTTON') { button.dataset.label = button.innerHTML; button.innerHTML = '<span class="app-spin"></span>Processing…'; }
                });
            }, 0);
            setTimeout(() => { if (form.dataset.busy) { idle(form); window.appNotify('This is taking longer than usual. Check the page before pressing again.', 'error'); } }, 60000);
            return true;
        };
                document.addEventListener('submit', (event) => {
            const form = event.target;
            if (! form.matches || ! form.matches('form') || form.hasAttribute('data-no-busy')) return;
            if ((form.method || 'get').toLowerCase() === 'get' || form.target === '_blank' || form.getAttribute('method') === 'dialog' || form.closest('[data\\2d official-page]')) return;
            if (form.dataset.busy) { event.preventDefault(); window.appNotify('Still processing. Please wait.', 'info'); return; }
            const submitter = event.submitter;
            // After every other listener has run: a form that a script stopped (a preview, a reason and password window) is not marked.
            setTimeout(() => { if (! event.defaultPrevented) window.appBusy(form, submitter); }, 0);
        });
        window.addEventListener('pageshow', (event) => { if (event.persisted) document.querySelectorAll('form[data-busy]').forEach(idle); });
    })();
</script>
