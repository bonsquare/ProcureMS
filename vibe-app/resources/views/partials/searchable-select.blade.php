{{-- Every dropdown becomes a type-to-search combo. The real <select> stays in the page (hidden) and remains the source of truth:
     forms submit it, page scripts read it, and picking fires its usual change event. Opt out with data-native. --}}
<style>
    select[data-ss-enhanced] { position: absolute !important; width: 1px !important; height: 1px !important; margin: 0 !important; padding: 0 !important; border: 0 !important; opacity: 0 !important; pointer-events: none !important; overflow: hidden !important; }
    input.ss-input { cursor: pointer; text-overflow: ellipsis; padding-right: 2rem; background-repeat: no-repeat; background-position: right .6rem center; background-size: 1rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); }
    input.ss-input:disabled { cursor: not-allowed; }
    input.ss-input[aria-invalid="true"] { border-color: #ba1a1a !important; }
    .ss-list { position: fixed; inset: auto; margin: 0; padding: .25rem; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: .5rem; background: #fff; color: #111827; box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
        font: 13px/1.35 Inter, system-ui, sans-serif; text-align: left; }
    .ss-option { padding: .4rem .6rem; border-radius: .35rem; cursor: pointer; overflow-wrap: anywhere; }
    .ss-option.is-active { background: #e4edf6; }
    .ss-option[aria-selected="true"] { font-weight: 700; }
    .ss-option[aria-disabled="true"] { color: #94a3b8; cursor: not-allowed; }
    .ss-option.is-placeholder { color: #64748b; }
    .ss-group { padding: .45rem .6rem .15rem; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
    .ss-hint { padding: .4rem .6rem; font-size: 12px; color: #64748b; }
</style>
<script data-searchable-select-script>
    (() => {
        if (window.searchableSelect || !('popover' in HTMLElement.prototype)) return;

        const EXCLUDE = 'select[multiple], select[data-native], .official-toolbar select';
        const MAX_OPTIONS = 500;
        const states = new WeakMap();
        let counter = 0;

        const fold = (text) => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const eligible = (select) => select instanceof HTMLSelectElement && !states.has(select) && !select.matches(EXCLUDE) && !(select.size > 1);

        const labelText = (select) => {
            if (select.getAttribute('aria-label')) return select.getAttribute('aria-label');
            const label = select.labels && select.labels[0];
            if (!label) return '';
            const copy = label.cloneNode(true);
            copy.querySelectorAll('select, input, textarea, .ss-list').forEach((node) => node.remove());
            return copy.textContent.replace(/\s+/g, ' ').trim();
        };

        const selectedText = (select) => {
            const option = select.options[select.selectedIndex];
            return option && option.value !== '' ? option.text : '';
        };

        const placeholderText = (select) => {
            const empty = Array.from(select.options).find((option) => option.value === '');
            return empty ? empty.text : '';
        };

        const enhance = (select) => {
            if (!eligible(select)) return;
            const id = 'ssl-' + (++counter);
            const input = document.createElement('input');
            input.type = 'text';
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-controls', id);
            const label = labelText(select);
            if (label) input.setAttribute('aria-label', label);

            const list = document.createElement('div');
            list.id = id;
            list.className = 'ss-list';
            list.setAttribute('popover', 'manual');
            list.setAttribute('role', 'listbox');

            const state = { select, input, list, typed: false, rendered: [], active: -1, host: select.closest('dialog') || document.body };
            states.set(select, state);

            select.setAttribute('data-ss-enhanced', '');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');
            select.insertAdjacentElement('afterend', input);
            state.host.appendChild(list);
            mirror(state);
            wire(state);
            state.observer = new MutationObserver(() => refresh(select));
            state.observer.observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'class', 'style', 'hidden', 'label'] });
            refresh(select);
        };

        // The visible box looks like the page's own fields: it takes over the select's classes, style and visibility.
        const mirror = (state) => {
            const { select, input } = state;
            input.className = 'ss-input ' + select.className;
            const style = select.getAttribute('style');
            if (style) input.setAttribute('style', style); else input.removeAttribute('style');
            input.hidden = select.hidden;
            input.disabled = select.disabled;
            input.required = false;
        };

        const refresh = (select) => {
            const state = states.get(select);
            if (!state) return;
            mirror(state);
            const text = selectedText(select);
            if (!state.typed || document.activeElement !== state.input) state.input.value = text;
            state.input.placeholder = placeholderText(select);
            if (state.list.matches(':popover-open')) render(state);
        };

        const isOpen = (state) => state.list.matches(':popover-open');

        const options = (select) => {
            const items = [];
            Array.from(select.children).forEach((child) => {
                if (child.tagName === 'OPTGROUP') {
                    items.push({ group: child.label, disabled: child.disabled });
                    Array.from(child.children).forEach((option) => items.push({ option, group: null, disabled: option.disabled || child.disabled, inGroup: true }));
                } else if (child.tagName === 'OPTION') {
                    items.push({ option: child, group: null, disabled: child.disabled });
                }
            });
            return items;
        };

        const render = (state) => {
            const { select, list } = state;
            const words = state.typed ? fold(state.input.value).split(/\s+/).filter(Boolean) : [];
            const matches = (text) => { const folded = fold(text); return words.every((word) => folded.includes(word)); };
            const items = options(select);
            list.replaceChildren();
            state.rendered = [];
            let shown = 0;
            let capped = false;
            let pendingGroup = null;
            items.forEach((item) => {
                if (capped) return;
                if (item.group !== null && item.group !== undefined && !item.option) {
                    pendingGroup = item.group;
                    return;
                }
                if (!matches(item.option.text)) return;
                if (shown >= MAX_OPTIONS) { capped = true; return; }
                if (item.inGroup && pendingGroup !== null) {
                    const heading = document.createElement('div');
                    heading.className = 'ss-group';
                    heading.setAttribute('role', 'presentation');
                    heading.textContent = pendingGroup;
                    list.appendChild(heading);
                    pendingGroup = null;
                }
                const row = document.createElement('div');
                row.className = 'ss-option' + (item.option.value === '' ? ' is-placeholder' : '');
                row.id = list.id + '-' + shown;
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', item.option.selected ? 'true' : 'false');
                if (item.disabled) row.setAttribute('aria-disabled', 'true');
                row.textContent = item.option.text || ' ';
                row.dataset.index = String(state.rendered.length);
                list.appendChild(row);
                state.rendered.push({ row, option: item.option, disabled: item.disabled });
                shown++;
            });
            if (!state.rendered.length) {
                const none = document.createElement('div');
                none.className = 'ss-hint';
                none.textContent = 'No matches';
                list.appendChild(none);
            }
            if (capped) {
                const hint = document.createElement('div');
                hint.className = 'ss-hint';
                hint.textContent = 'Type to narrow the list';
                list.appendChild(hint);
            }
            const selected = state.rendered.findIndex((entry) => entry.option.selected && !entry.disabled);
            setActive(state, state.typed || selected < 0 ? state.rendered.findIndex((entry) => !entry.disabled) : selected);
            position(state);
        };

        const setActive = (state, index) => {
            state.active = index;
            state.rendered.forEach((entry, i) => entry.row.classList.toggle('is-active', i === index));
            const active = state.rendered[index];
            if (active) {
                state.input.setAttribute('aria-activedescendant', active.row.id);
                active.row.scrollIntoView({ block: 'nearest' });
            } else {
                state.input.removeAttribute('aria-activedescendant');
            }
        };

        const move = (state, step) => {
            const count = state.rendered.length;
            if (!count) return;
            let index = state.active;
            for (let tries = 0; tries < count; tries++) {
                index = (index + step + count) % count;
                if (!state.rendered[index].disabled) { setActive(state, index); return; }
            }
        };

        const position = (state) => {
            const { input, list } = state;
            if (!isOpen(state)) return;
            const rect = input.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom;
            const above = rect.top;
            const openUp = below < 180 && above > below;
            list.style.width = Math.max(rect.width, 160) + 'px';
            list.style.left = Math.min(rect.left, Math.max(0, window.innerWidth - Math.max(rect.width, 160) - 8)) + 'px';
            list.style.maxHeight = Math.max(120, Math.min(256, (openUp ? above : below) - 12)) + 'px';
            if (openUp) { list.style.top = 'auto'; list.style.bottom = (window.innerHeight - rect.top + 4) + 'px'; }
            else { list.style.bottom = 'auto'; list.style.top = (rect.bottom + 4) + 'px'; }
        };

        const open = (state, typed) => {
            if (state.input.disabled) return;
            state.typed = typed;
            if (!isOpen(state)) state.list.showPopover();
            state.input.setAttribute('aria-expanded', 'true');
            render(state);
        };

        const close = (state, restore = true) => {
            if (isOpen(state)) state.list.hidePopover();
            state.input.setAttribute('aria-expanded', 'false');
            state.input.removeAttribute('aria-activedescendant');
            state.typed = false;
            if (restore) state.input.value = selectedText(state.select);
        };

        const pick = (state, entry) => {
            if (!entry || entry.disabled) return;
            const { select } = state;
            select.selectedIndex = entry.option.index;
            state.input.removeAttribute('aria-invalid');
            close(state);
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const wire = (state) => {
            const { select, input, list } = state;

            input.addEventListener('click', () => { if (isOpen(state)) close(state); else { open(state, false); input.select(); } });
            input.addEventListener('input', () => open(state, true));
            input.addEventListener('blur', () => close(state));
            select.addEventListener('focus', () => input.focus());
            // Later than the browser's own focusing of the first invalid control, so the user ends up in the visible box.
            select.addEventListener('invalid', () => { input.setAttribute('aria-invalid', 'true'); setTimeout(() => input.focus(), 0); });

            input.addEventListener('keydown', (event) => {
                const key = event.key;
                if (key === 'ArrowDown' || key === 'ArrowUp') {
                    event.preventDefault();
                    if (!isOpen(state)) open(state, false); else move(state, key === 'ArrowDown' ? 1 : -1);
                } else if (key === 'Enter') {
                    event.preventDefault();
                    if (isOpen(state)) pick(state, state.rendered[state.active]);
                } else if (key === 'Escape') {
                    if (isOpen(state)) { event.preventDefault(); event.stopPropagation(); close(state); }
                } else if (key === 'Tab') {
                    if (isOpen(state) && state.typed) pick(state, state.rendered[state.active]); else if (isOpen(state)) close(state);
                } else if ((key === 'Home' || key === 'End') && isOpen(state) && !state.typed) {
                    event.preventDefault();
                    const enabled = state.rendered.map((entry, i) => (entry.disabled ? -1 : i)).filter((i) => i >= 0);
                    if (enabled.length) setActive(state, key === 'Home' ? enabled[0] : enabled[enabled.length - 1]);
                }
            });

            // Keep focus in the box while the list is used with the mouse or a finger.
            list.addEventListener('mousedown', (event) => event.preventDefault());
            list.addEventListener('click', (event) => {
                const row = event.target.closest('.ss-option');
                if (row) pick(state, state.rendered[Number(row.dataset.index)]);
            });
            list.addEventListener('mousemove', (event) => {
                const row = event.target.closest('.ss-option');
                if (row && !row.hasAttribute('aria-disabled')) setActive(state, Number(row.dataset.index));
            });
        };

        const enhanceAll = (root) => {
            (root.matches && root.matches('select') ? [root] : []).concat(Array.from(root.querySelectorAll ? root.querySelectorAll('select') : [])).forEach(enhance);
        };

        // A select that left the page takes its box and list with it (no orphans after a table row is removed).
        const cleanup = (node) => {
            const selects = (node.matches && node.matches('select') ? [node] : []).concat(Array.from(node.querySelectorAll ? node.querySelectorAll('select[data-ss-enhanced]') : []));
            selects.forEach((select) => {
                const state = states.get(select);
                if (!state || document.contains(select)) return;
                state.observer.disconnect();
                state.input.remove();
                state.list.remove();
                states.delete(select);
                select.removeAttribute('data-ss-enhanced');
            });
        };

        // Page scripts that set the value in code: keep the visible text in step.
        ['value', 'selectedIndex'].forEach((property) => {
            const descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, property);
            if (!descriptor || !descriptor.set) return;
            Object.defineProperty(HTMLSelectElement.prototype, property, {
                configurable: true,
                enumerable: descriptor.enumerable,
                get: descriptor.get,
                set(value) { descriptor.set.call(this, value); if (states.has(this)) refresh(this); },
            });
        });
        const selectedDescriptor = Object.getOwnPropertyDescriptor(HTMLOptionElement.prototype, 'selected');
        if (selectedDescriptor && selectedDescriptor.set) {
            Object.defineProperty(HTMLOptionElement.prototype, 'selected', {
                configurable: true,
                enumerable: selectedDescriptor.enumerable,
                get: selectedDescriptor.get,
                set(value) { selectedDescriptor.set.call(this, value); const owner = this.closest('select'); if (owner && states.has(owner)) refresh(owner); },
            });
        }

        document.addEventListener('reset', (event) => {
            setTimeout(() => { if (event.target.querySelectorAll) event.target.querySelectorAll('select[data-ss-enhanced]').forEach((select) => refresh(select)); }, 0);
        }, true);

        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.removedNodes.forEach((node) => { if (node.nodeType === 1) cleanup(node); });
                mutation.addedNodes.forEach((node) => { if (node.nodeType === 1) enhanceAll(node); });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });

        window.searchableSelect = { refresh, enhance, enhanceAll };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => enhanceAll(document)); else enhanceAll(document);
    })();
</script>
