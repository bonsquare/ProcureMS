{{-- Screen-only controls for an official document. Rendered outside the document; hidden when printing. --}}
<div class="official-toolbar no-print" role="toolbar" aria-label="Official document actions">
    @isset($context)<span class="hint" style="font-weight:700">{{ $context }}</span>@endisset
    <a class="btn" href="{{ $closeUrl ?? url()->previous() }}" onclick="event.preventDefault(); closePrintTab(this.href);">Close</a>
    <label for="op-paper">Paper Size
        <select id="op-paper">
            <option value="a4">A4 (210 × 297 mm)</option>
            <option value="letter">Letter / Short Bond (215.9 × 279.4 mm)</option>
            <option value="legal">Legal (215.9 × 355.6 mm)</option>
            <option value="longbond">Long Bond (215.9 × 330.2 mm)</option>
            <option value="folio">Folio (210 × 330 mm)</option>
            <option value="custom">Custom</option>
        </select>
    </label>
    <span id="op-custom" hidden>
        <label for="op-width">Width <input id="op-width" type="number" min="50" max="1000" step="0.1"> mm</label>
        <label for="op-height">Height <input id="op-height" type="number" min="50" max="1000" step="0.1"> mm</label>
    </span>
    <label for="op-orientation">Orientation
        <select id="op-orientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select>
    </label>
    <label for="op-zoom">Zoom
        <select id="op-zoom"><option value="fit">Fit width</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option><option value="1.25">125%</option></select>
    </label>
    <span class="spacer"></span>
    <span class="hint">In the print dialog keep Margins on Default and turn off Headers and footers.</span>
    <button type="button" class="primary" id="op-print">Print / Save as PDF</button>
</div>
