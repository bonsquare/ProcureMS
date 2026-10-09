{{-- Screen-only controls for an official document. Rendered outside the document; hidden when printing. --}}
<div class="official-toolbar no-print" role="toolbar" aria-label="Official document actions">
    <div class="ot-left">
        <a class="ot-btn ot-btn--ghost btn" href="{{ $closeUrl ?? url()->previous() }}" onclick="event.preventDefault(); closePrintTab(this.href);">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Close
        </a>
        <span class="ot-divider" aria-hidden="true"></span>
        <div class="ot-title">
            <span class="ot-eyebrow">Print preview</span>
            <strong>{{ $context ?? 'Official document' }}</strong>
        </div>
    </div>

    <div class="ot-right">
        <details class="ot-setup">
            <summary class="ot-btn ot-btn--icon" title="Page setup: paper size, orientation, zoom" aria-label="Page setup">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></svg>
            </summary>
            <div class="ot-pop">
                <p class="ot-pop__title">Page setup</p>
                <div class="ot-fields">
                    <label class="ot-field" for="op-paper"><span>Paper Size</span>
                        <select id="op-paper">
                            <option value="a4">A4 (210 × 297 mm)</option>
                            <option value="letter">Letter / Short Bond (215.9 × 279.4 mm)</option>
                            <option value="legal">Legal (215.9 × 355.6 mm)</option>
                            <option value="longbond">Long Bond (215.9 × 330.2 mm)</option>
                            <option value="folio">Folio (210 × 330 mm)</option>
                            <option value="custom">Custom</option>
                        </select>
                    </label>
                    <span id="op-custom" class="ot-custom" hidden>
                        <label class="ot-field" for="op-width"><span>Width (mm)</span><input id="op-width" type="number" min="50" max="1000" step="0.1"></label>
                        <label class="ot-field" for="op-height"><span>Height (mm)</span><input id="op-height" type="number" min="50" max="1000" step="0.1"></label>
                    </span>
                    <div class="ot-row">
                        <label class="ot-field" for="op-orientation"><span>Orientation</span>
                            <select id="op-orientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select>
                        </label>
                        <label class="ot-field" for="op-zoom"><span>Zoom</span>
                            <select id="op-zoom"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option><option value="1.25">125%</option></select>
                        </label>
                    </div>
                </div>
                <p class="ot-pop__hint"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>In the print dialog keep Margins on Default and turn off Headers and footers.</p>
            </div>
        </details>
        <button type="button" class="ot-btn ot-btn--primary primary" id="op-print">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>Print / Save as PDF
        </button>
    </div>
</div>
<script>
    // The page-setup popover closes on an outside click or Escape.
    (() => {
        const setup = document.querySelector('.ot-setup');
        if (! setup) return;
        document.addEventListener('click', (event) => { if (setup.open && ! setup.contains(event.target)) setup.open = false; });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setup.open = false; });
    })();
</script>
