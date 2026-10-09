{{--
    Shared print engine for every official document.
    - Screen: grey desk, white sheet sized to the selected paper, plain toolbar (partials.official-toolbar).
    - Print: white page, black text, no UI. The paper size and orientation come from the toolbar and are written to @page here.
    A document opts in by putting data-official-page on its page element(s):
        data-doc="rfq"                 key used to remember the user's paper choice for this kind of document
        data-paper="a4|letter|legal|longbond|folio"   the form's normal paper (default a4)
        data-orientation="portrait|landscape"
        data-margin="10"               page margin in mm for forms that rely on @page margins (default 0: the form carries its own)
        data-single-page               fixed one-page forms (scaled to fit the sheet rather than paginated)
--}}
<style id="official-print-base">
    :root {
        --doc-font: Arial, "Helvetica", sans-serif;
        --doc-border: 0.75pt solid #000;
        --doc-border-strong: 1pt solid #000;
        --doc-cell-padding: 2px 4px;
        --doc-text: 9pt;
        --doc-small: 7.5pt;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { background: radial-gradient(1100px 360px at 50% -80px, #d3e2f2 0%, rgba(211, 226, 242, 0) 72%), #e6ebf1; }

    /* ---- screen toolbar: UI only, never printed ---- */
    .official-toolbar { position: sticky; top: 0; z-index: 1000; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 18px; color: #fff;
        background: linear-gradient(135deg, #0b2a66 0%, #103967 55%, #1b5088 100%); border-bottom: 3px solid #369878; box-shadow: 0 4px 16px rgba(11, 42, 102, .25);
        font: 13px/1.3 Inter, "Segoe UI", system-ui, -apple-system, Arial, sans-serif; }
    .official-toolbar * { box-sizing: border-box; }
    .ot-left, .ot-right { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .ot-divider { width: 1px; height: 28px; background: rgba(255, 255, 255, .22); }
    .ot-title { display: flex; flex-direction: column; min-width: 0; }
    .ot-title strong { font-size: 14px; font-weight: 700; letter-spacing: .01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 46vw; }
    .ot-eyebrow { font-size: 9.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #8fd5bb; }
    .ot-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 38px; padding: 0 16px; border-radius: 10px; border: 1px solid transparent;
        font: 700 13px Inter, "Segoe UI", system-ui, Arial, sans-serif; cursor: pointer; text-decoration: none; list-style: none; transition: transform .12s, box-shadow .15s, background .15s; }
    .ot-btn--ghost, .ot-btn--icon { color: #fff; background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .25); }
    .ot-btn--ghost:hover, .ot-btn--icon:hover { background: rgba(255, 255, 255, .2); }
    .ot-btn--icon { width: 38px; padding: 0; }
    .ot-btn--primary { color: #fff; background: linear-gradient(180deg, #3fae8a, #2a7f64); box-shadow: 0 4px 14px rgba(42, 127, 100, .45), inset 0 1px 0 rgba(255, 255, 255, .25); padding: 0 20px; }
    .ot-btn--primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(42, 127, 100, .55), inset 0 1px 0 rgba(255, 255, 255, .3); }
    .ot-btn--primary:active { transform: translateY(0); }
    .ot-btn:focus-visible { outline: 3px solid #8fd5bb; outline-offset: 2px; }

    /* page setup popover: paper size, orientation, zoom (hidden until the gear is clicked) */
    .ot-setup { position: relative; }
    .ot-setup > summary::-webkit-details-marker { display: none; }
    .ot-setup[open] > summary { background: rgba(255, 255, 255, .24); }
    .ot-pop { position: absolute; right: 0; top: calc(100% + 10px); width: 320px; padding: 14px 16px 12px; border-radius: 14px; background: #fff; color: #0f172a; border: 1px solid #dbe4ec; box-shadow: 0 18px 44px rgba(15, 23, 42, .28); z-index: 5; }
    .ot-pop__title { margin: 0 0 10px; font-size: 13px; font-weight: 800; color: #103967; }
    .ot-fields { display: flex; flex-direction: column; gap: 10px; }
    .ot-row, .ot-custom { display: flex; gap: 10px; }
    .ot-row > .ot-field, .ot-custom > .ot-field { flex: 1; }
    .ot-field { display: flex; flex-direction: column; gap: 4px; margin: 0; }
    .ot-field > span { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #536273; }
    .ot-pop select, .ot-pop input[type="number"] { width: 100%; height: 36px; padding: 0 30px 0 10px; border: 1px solid #cbd7e1; border-radius: 9px; background-color: #f4f7fa; color: #0f172a;
        font: 600 12.5px Inter, "Segoe UI", system-ui, Arial, sans-serif; outline: none; transition: border-color .15s, box-shadow .15s, background .15s; }
    .ot-pop select { appearance: none; -webkit-appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23103967' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 11px center; }
    .ot-pop input[type="number"] { padding-right: 10px; }
    .ot-pop select:hover, .ot-pop input[type="number"]:hover { background-color: #eaf0f6; }
    .ot-pop select:focus-visible, .ot-pop input[type="number"]:focus-visible { border-color: #286da8; box-shadow: 0 0 0 3px rgba(40, 109, 168, .2); background-color: #fff; }
    .ot-pop__hint { display: flex; gap: 6px; margin: 12px 0 0; padding-top: 10px; border-top: 1px solid #e3eaf1; font-size: 11px; line-height: 1.35; color: #536273; }
    .ot-pop__hint svg { flex: none; margin-top: 1px; color: #286da8; }
    .official-toolbar [hidden] { display: none !important; }
    @media (max-width: 560px) { .official-toolbar { padding: 8px 10px; } .ot-divider, .ot-eyebrow { display: none; } .ot-btn--primary { padding: 0 14px; } .ot-title strong { max-width: 32vw; } .ot-pop { right: -4px; width: min(320px, 92vw); } }

    /* ---- screen preview of the sheet ---- */
    .official-sheet { position: relative; margin: 24px auto; background: #fff; border-radius: 2px; box-shadow: 0 1px 2px rgba(15, 23, 42, .14), 0 10px 32px rgba(15, 23, 42, .2); zoom: var(--preview-zoom, 1);
        background-image: repeating-linear-gradient(to bottom, rgba(0,0,0,0) 0, rgba(0,0,0,0) calc(var(--sheet-h) - 1px), #b8bcc6 calc(var(--sheet-h) - 1px), #b8bcc6 var(--sheet-h)); }
    .official-sheet > [data-official-page] { margin-left: auto !important; margin-right: auto !important; }
    img.official-logo { object-fit: contain; }

    /* ---- print ---- */
    @media print {
        html, body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .official-toolbar, .no-print { display: none !important; }
        .official-sheet { margin: 0 !important; padding: 0 !important; width: auto !important; min-height: 0 !important; zoom: 1 !important; background: #fff !important; background-image: none !important; box-shadow: none !important; border-radius: 0 !important; break-after: page; page-break-after: always; }
        .official-sheet:last-of-type { break-after: auto; page-break-after: auto; }
        [data-official-page] { box-shadow: none !important; border-radius: 0 !important; margin-top: 0 !important; margin-bottom: 0 !important; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr, img, svg, .signature, .signatures, .signature-block, [class*="signator"], [class*="approval"], [class*="certif"], [class*="summary"] { break-inside: avoid; page-break-inside: avoid; }
        table { border-collapse: collapse; }
        td, th { overflow-wrap: break-word; }
    }
</style>
<script>
    function closePrintTab(fallbackUrl) {
        window.close();
        // Browsers only close tabs that were opened from a link. If this one wasn't, return to the page it belongs to.
        setTimeout(function () { if (!window.closed) window.location.href = fallbackUrl; }, 250);
    }

    (function () {
        var MM = 96 / 25.4;
        var PAPERS = { a4: [210, 297], letter: [215.9, 279.4], legal: [215.9, 355.6], longbond: [215.9, 330.2], folio: [210, 330] };
        var pageStyle = null;

        function store(key, value) { try { if (value === undefined) { return JSON.parse(localStorage.getItem(key)); } localStorage.setItem(key, JSON.stringify(value)); } catch (e) { return null; } }

        document.addEventListener('DOMContentLoaded', function () {
            var pages = Array.prototype.slice.call(document.querySelectorAll('[data-official-page]'));
            if (!pages.length) { return; }
            var first = pages[0];
            var docKey = 'officialPrint.' + (first.dataset.doc || document.title.split(' ')[0] || 'doc');
            var single = first.hasAttribute('data-single-page');
            var baseMargin = parseFloat(first.dataset.margin || '0');
            var state = Object.assign({ paper: first.dataset.paper || 'a4', orientation: first.dataset.orientation || 'portrait', width: 215.9, height: 330.2, zoom: 'fit' }, store(docKey) || {});
            // ?paper=a4&orientation=landscape (or paper=custom&width=&height=) opens the document on that paper.
            var query = new URLSearchParams(location.search);
            var fromUrl = false;
            ['paper', 'orientation', 'width', 'height'].forEach(function (key) { if (query.get(key)) { state[key] = query.get(key); fromUrl = true; } });
            if (!PAPERS[state.paper] && state.paper !== 'custom') { state.paper = 'a4'; }

            pageStyle = document.createElement('style');
            pageStyle.id = 'official-page-style';
            document.head.appendChild(pageStyle);

            // Wrap every page in a sheet. "fills" are the blocks that carry a page-height min-height (the printed form
            // itself); "tops" are the outermost ones, i.e. one per physical form page.
            var sheets = pages.map(function (page) {
                var sheet = document.createElement('div');
                sheet.className = 'official-sheet';
                page.parentNode.insertBefore(sheet, page);
                sheet.appendChild(page);
                var fills = [page].concat(Array.prototype.slice.call(page.querySelectorAll('*'))).filter(function (node) {
                    return parseFloat(getComputedStyle(node).minHeight) > 700;
                });
                var tops = fills.filter(function (node) {
                    return !fills.some(function (other) { return other !== node && other.contains(node); });
                });
                return { sheet: sheet, page: page, fills: fills, tops: tops };
            });

            var controls = {
                paper: document.getElementById('op-paper'), orient: document.getElementById('op-orientation'), width: document.getElementById('op-width'),
                height: document.getElementById('op-height'), custom: document.getElementById('op-custom'), zoom: document.getElementById('op-zoom'),
            };

            function paperMm() {
                var size = state.paper === 'custom' ? [Math.max(50, +state.width || 210), Math.max(50, +state.height || 297)] : PAPERS[state.paper];
                return state.orientation === 'landscape' ? [size[1], size[0]] : size.slice();
            }

            function measure(item, pw) {
                // Measure at true size: the on-screen preview zoom must not leak into the numbers.
                document.documentElement.style.setProperty('--preview-zoom', 1);
                item.fills.forEach(function (node) { node.style.minHeight = '0'; });
                item.page.style.zoom = 1;
                item.sheet.style.width = pw + 'mm';
                return {
                    width: Math.max(item.page.scrollWidth, item.page.getBoundingClientRect().width),
                    height: Math.max(item.page.scrollHeight, item.page.getBoundingClientRect().height),
                    topHeight: Math.max.apply(null, item.tops.map(function (node) { return node.getBoundingClientRect().height; }).concat([0])),
                };
            }

            // A form is "paged" when each of its form pages fits one sheet of its own paper (a tolerance of 4% absorbs
            // forms drawn a hair taller than the paper). Paged forms are scaled to fit any sheet, one form page per sheet,
            // so nothing spills a few lines onto an extra page. Anything longer (item lists, reports) flows over pages.
            var paged = single;
            if (!paged) {
                var base = first.dataset.paper && PAPERS[first.dataset.paper] ? PAPERS[first.dataset.paper] : PAPERS.a4;
                if (first.dataset.orientation === 'landscape') { base = [base[1], base[0]]; }
                paged = sheets.every(function (item) {
                    var m = measure(item, base[0]);
                    return item.tops.length > 0 && m.topHeight <= (base[1] - 2 * baseMargin) * MM * 1.04;
                });
            }

            function layout() {
                var dims = paperMm(), pw = dims[0], ph = dims[1];
                var marginX = baseMargin, marginY = baseMargin;
                var result = sheets.map(function (item) {
                    var m = measure(item, pw);
                    var zoom = Math.min(1, ((pw - 2 * marginX) * MM) / m.width);
                    // 3% headroom: text re-wraps slightly when scaled, so a form scaled to the exact sheet height can overflow by a line.
                    if (paged) { zoom = Math.min(zoom, ((ph - 2 * marginY) * MM) / ((single ? m.height : m.topHeight) * 1.03)); }
                    return { item: item, zoom: zoom, naturalH: m.height };
                });
                // A form that runs past one page gets a small top/bottom page margin so continuation pages never touch the paper edge.
                var flows = !paged && result.some(function (r) { return r.naturalH * r.zoom > (ph - 2 * marginY) * MM + 2; });
                if (flows) { marginY = Math.max(marginY, 8); }
                result.forEach(function (r) {
                    var availableH = (ph - 2 * marginY) * MM - 3;
                    r.item.page.style.zoom = r.zoom;
                    r.item.fills.forEach(function (node) { node.style.minHeight = Math.max(0, availableH / r.zoom) + 'px'; });
                    if (paged) { r.item.tops.forEach(function (node, index) { node.style.breakBefore = index ? 'page' : ''; }); }
                    r.item.sheet.style.minHeight = ph + 'mm';
                    r.item.sheet.style.padding = marginY + 'mm ' + marginX + 'mm';
                    r.item.sheet.style.setProperty('--sheet-h', ph + 'mm');
                });
                pageStyle.textContent = '@page { size: ' + pw + 'mm ' + ph + 'mm; margin: ' + marginY + 'mm ' + marginX + 'mm; }';
                var preview = state.zoom === 'fit' ? Math.min(1, (window.innerWidth - 40) / (pw * MM)) : (+state.zoom || 1);
                document.documentElement.style.setProperty('--preview-zoom', preview);
            }

            function sync() {
                if (controls.paper) { controls.paper.value = state.paper; }
                if (controls.orient) { controls.orient.value = state.orientation; }
                if (controls.width) { controls.width.value = state.width; }
                if (controls.height) { controls.height.value = state.height; }
                if (controls.custom) { controls.custom.hidden = state.paper !== 'custom'; }
                if (controls.zoom) { controls.zoom.value = String(state.zoom); }
                layout();
                if (!fromUrl) { store(docKey, state); }
            }

            if (controls.paper) { controls.paper.addEventListener('change', function () { state.paper = controls.paper.value; sync(); }); }
            if (controls.orient) { controls.orient.addEventListener('change', function () { state.orientation = controls.orient.value; sync(); }); }
            if (controls.width) { controls.width.addEventListener('input', function () { state.width = controls.width.value; sync(); }); }
            if (controls.height) { controls.height.addEventListener('input', function () { state.height = controls.height.value; sync(); }); }
            if (controls.zoom) { controls.zoom.addEventListener('change', function () { state.zoom = controls.zoom.value; sync(); }); }
            var printButton = document.getElementById('op-print');
            if (printButton) { printButton.addEventListener('click', function () { layout(); window.print(); }); }
            window.addEventListener('beforeprint', layout);
            window.addEventListener('resize', function () { if (state.zoom === 'fit') { layout(); } });
            // Fonts and logos load after DOMContentLoaded and can change the natural size.
            window.addEventListener('load', layout);
            sync();
        });
    })();
</script>
