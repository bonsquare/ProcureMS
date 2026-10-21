{{-- Number inputs: no up/down arrows, and the mouse wheel or arrow keys never change the value by accident. --}}
<style>
    input[type=number] { -moz-appearance: textfield; appearance: textfield; }
    input[type=number]::-webkit-outer-spin-button, input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
<script>
    document.addEventListener('wheel', (event) => { const field = document.activeElement; if (field && field.type === 'number' && field === event.target) field.blur(); }, { passive: true });
    document.addEventListener('keydown', (event) => { if ((event.key === 'ArrowUp' || event.key === 'ArrowDown') && event.target.type === 'number') event.preventDefault(); });
</script>

@include('partials.apple-design')
@include('partials.trial-banner')
@include('partials.password-toggle')
@include('partials.searchable-select')

{{-- Windows (modals and dialogs) open beside the sidebar, never over it, and every one of them has a close button (X). --}}
<style>
    @media (min-width: 768px) {
        body:has(aside.fixed.left-0) [role="dialog"].fixed { left: 15rem; }
        body:has(aside.fixed.left-0) dialog[open] { left: 15rem; right: 0; max-width: calc(100vw - 15rem - 1.5rem); }
        body:has(aside.fixed.left-0) dialog[data-split] { width: min(calc(100vw - 15rem - 1.5rem), 1320px) !important; }
    }
    .modal-x { position: absolute; top: .5rem; right: .5rem; z-index: 5; display: inline-flex; height: 2.25rem; width: 2.25rem; align-items: center; justify-content: center; border-radius: .5rem; background: #fff; color: #536273; box-shadow: 0 0 0 1px #cbd7e1; }
    .modal-x:hover { background: #103967; color: #fff; }
    dialog[data-split] .split-bar, #ors-preview-bar, #dv-preview-bar { padding-right: 3.25rem; }
</style>
<script>
    (() => {
        const hasClose = (modal) => Array.from(modal.querySelectorAll('button')).some((button) => button.matches('[data-modal-x]') || button.getAttribute('aria-label') === 'Close' || /^\s*close\s*$/i.test(button.textContent));
        const closeModal = (modal) => {
            if (modal.tagName === 'DIALOG') { modal.close(); return; }
            const closer = modal.querySelector('[data-close], [data-close-ors], [data-close-account], [data-close-modal]')
                || Array.from(modal.querySelectorAll('button')).find((button) => /^\s*(cancel|close)\s*$/i.test(button.textContent));
            if (closer) { closer.click(); return; }
            modal.classList.add('hidden'); modal.classList.remove('flex');
        };
        const add = (modal) => {
            if (hasClose(modal)) return;
            const panel = modal.tagName === 'DIALOG' ? modal : modal.firstElementChild;
            if (! panel) return;
            if (getComputedStyle(panel).position === 'static') panel.style.position = 'relative';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'modal-x';
            button.setAttribute('data-modal-x', '');
            button.setAttribute('aria-label', 'Close');
            button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">close</span>';
            button.addEventListener('click', () => closeModal(modal));
            panel.appendChild(button);
        };
        const run = () => document.querySelectorAll('dialog, [role="dialog"]').forEach(add);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    })();
</script>

{{-- The disclosure arrow of every collapsible section sits on the left of its title. --}}
<style>
    .chev-left > .sum-chevron { margin-right: .25rem; }
    [data-app-cat] .sum-chevron, .bg-primary > .sum-chevron { color: #fff; }
    .chev-left > .sum-chevron + * { margin-right: auto; }
</style>
<script>
    (() => {
        const run = () => document.querySelectorAll('.sum-chevron').forEach((arrow) => {
            const host = arrow.closest('summary') || arrow.closest('[data-head] td > span, [data-app-cat] td > span');
            if (! host || host.classList.contains('chev-left')) return;
            host.classList.add('chev-left');
            host.insertBefore(arrow, host.firstChild);
        });
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    })();
</script>
