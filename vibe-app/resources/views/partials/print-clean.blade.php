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
    body { background: #e5e7eb; }

    /* ---- screen toolbar: UI only, never printed ---- */
    .official-toolbar { position: sticky; top: 0; z-index: 1000; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 8px 16px; background: #fff; border-bottom: 1px solid #c8ccd4; font: 12px Arial, Helvetica, sans-serif; color: #111; }
    .official-toolbar label { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
    .official-toolbar select, .official-toolbar input[type="number"] { height: 28px; padding: 0 6px; border: 1px solid #9aa0ab; border-radius: 2px; background: #fff; font: 12px Arial, Helvetica, sans-serif; color: #111; }
    .official-toolbar input[type="number"] { width: 70px; }
    .official-toolbar button, .official-toolbar a.btn { height: 30px; padding: 0 14px; border: 1px solid #111; border-radius: 2px; background: #fff; color: #111; font: 600 12px Arial, Helvetica, sans-serif; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
    .official-toolbar button.primary { background: #111; color: #fff; }
    .official-toolbar .spacer { flex: 1 1 auto; }
    .official-toolbar .hint { color: #4b5160; font-size: 11px; }
    .official-toolbar [hidden] { display: none !important; }

    /* ---- screen preview of the sheet ---- */
    .official-sheet { position: relative; margin: 18px auto; background: #fff; box-shadow: 0 1px 6px rgba(0, 0, 0, .28); zoom: var(--preview-zoom, 1);
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
